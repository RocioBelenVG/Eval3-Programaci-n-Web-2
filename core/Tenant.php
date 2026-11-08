<?php
/**
 * TriTransportes — Aislamiento multiempresa (multi-tenancy)
 * ----------------------------------------------------------------------------
 * En un SaaS multiempresa el riesgo mayor no es que una consulta falle, sino que
 * devuelva datos de OTRO cliente porque alguien olvidó el `WHERE empresa_id = ?`.
 * Esta clase deja de confiar en la memoria del programador: inspecciona cada
 * consulta y avisa (o bloquea) cuando toca una tabla con `empresa_id` sin
 * filtrarla.
 *
 * Modos (constante TENANT_MODO en config.php):
 *   'off'       → no revisa nada.
 *   'auditar'   → registra la consulta sospechosa en logs/tenant.log y continúa.
 *                 Es el modo seguro para producción: nunca rompe una pantalla.
 *   'estricto'  → lanza excepción. Pensado para desarrollo y pruebas.
 *
 * La detección es deliberadamente conservadora: ante la duda, no acusa.
 */
class Tenant
{
    /** Consultas ya reportadas en esta ejecución (evita inundar el log). */
    private static array $reportadas = [];

    /** Evita reentrada: la propia clase consulta la BD para leer el esquema. */
    private static bool $ocupado = false;

    /** Caché de las tablas que tienen columna empresa_id. */
    private static ?array $tablasTenant = null;

    /**
     * Tablas con empresa_id. Se leen del esquema una vez y quedan en caché de
     * archivo; si el esquema no puede consultarse, se usa la lista conocida.
     */
    public static function tablasTenant(): array
    {
        if (self::$tablasTenant !== null) return self::$tablasTenant;

        $cache = ROOT . '/logs/.tenant_tablas.php';
        if (is_file($cache) && (time() - filemtime($cache)) < 86400) {
            $datos = @include $cache;
            if (is_array($datos) && $datos) {
                return self::$tablasTenant = $datos;
            }
        }

        try {
            $filas = DB::all(
                "SELECT TABLE_NAME AS t FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'empresa_id'"
            );
            $lista = array_column($filas, 't');
        } catch (Throwable) {
            $lista = [];
        }

        if (!$lista) {
            // Respaldo: tablas conocidas del modelo de datos
            $lista = [
                'sucursales', 'trabajadores', 'clientes', 'obras', 'contratos', 'vehiculos',
                'equipos', 'proveedores_combustible', 'estanques', 'pozos', 'usuarios',
                'auditoria', 'cargas_combustible', 'mantenciones', 'detenciones_equipo',
                'partes_diarios', 'viajes', 'guias_despacho', 'produccion_aridos',
                'stock_aridos', 'despachos_aridos', 'documentos', 'checklists_registros',
                'incidentes',
            ];
        }

        @file_put_contents($cache, '<?php return ' . var_export($lista, true) . ';');
        return self::$tablasTenant = $lista;
    }

    /**
     * Revisa una consulta. Devuelve null si está bien, o el motivo del problema.
     */
    public static function revisar(string $sql): ?string
    {
        $limpio = self::normalizar($sql);
        $verbo  = strtoupper(strtok(ltrim($limpio), " \t\n("));

        if (!in_array($verbo, ['SELECT', 'UPDATE', 'DELETE', 'INSERT'], true)) return null;

        $tablas = self::tablasReferenciadas($limpio, $verbo);
        if (!$tablas) return null;

        $sensibles = array_intersect($tablas, self::tablasTenant());
        if (!$sensibles) return null;

        // Basta con que la consulta mencione empresa_id: puede filtrarse por la
        // propia tabla o a través de un JOIN con su tabla padre.
        if (stripos($limpio, 'empresa_id') !== false) return null;

        // Las consultas sobre information_schema no operan datos de negocio
        if (stripos($limpio, 'information_schema') !== false) return null;

        // Marcador explícito para los casos en que el identificador ya fue
        // validado contra la empresa antes de llegar aquí:  /* tenant-ok: ... */
        if (stripos($sql, 'tenant-ok') !== false) return null;

        return 'Consulta sobre ' . implode(', ', $sensibles) . ' sin filtro empresa_id';
    }

    /**
     * Punto de entrada usado por DB::run(). Según el modo configurado, registra
     * o interrumpe.
     */
    public static function verificar(string $sql): void
    {
        $modo = defined('TENANT_MODO') ? TENANT_MODO : 'auditar';
        if ($modo === 'off' || self::$ocupado) return;

        self::$ocupado = true;
        try {
            $motivo = self::revisar($sql);
            if ($motivo === null) return;

            if ($modo === 'estricto') {
                throw new RuntimeException('[Aislamiento multiempresa] ' . $motivo . ' :: ' . self::normalizar($sql));
            }

            self::registrar($motivo, $sql);
        } finally {
            self::$ocupado = false;
        }
    }

    /** Escribe el hallazgo en logs/tenant.log, una vez por consulta distinta. */
    private static function registrar(string $motivo, string $sql): void
    {
        $firma = md5($sql);
        if (isset(self::$reportadas[$firma])) return;
        self::$reportadas[$firma] = true;

        $origen = 'desconocido';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $paso) {
            if (!isset($paso['file'])) continue;
            if (str_contains($paso['file'], 'core' . DIRECTORY_SEPARATOR . 'Tenant.php')) continue;
            if (str_contains($paso['file'], 'config' . DIRECTORY_SEPARATOR . 'database.php')) continue;
            $origen = str_replace(ROOT, '', $paso['file']) . ':' . ($paso['line'] ?? 0);
            break;
        }

        $linea = sprintf(
            "[%s] %s | origen: %s | usuario: %d | empresa: %d | %s\n",
            date('Y-m-d H:i:s'),
            $motivo,
            $origen,
            class_exists('Auth') ? Auth::id() : 0,
            class_exists('Auth') ? Auth::empresaId() : 0,
            self::normalizar($sql)
        );

        @file_put_contents(ROOT . '/logs/tenant.log', $linea, FILE_APPEND);
    }

    // ── Análisis del SQL ────────────────────────────────────────────────────

    /** Colapsa espacios y quita comentarios para poder analizar el texto. */
    public static function normalizar(string $sql): string
    {
        $sql = preg_replace('/--[^\n]*/', ' ', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql);
        return trim(preg_replace('/\s+/', ' ', $sql));
    }

    /** Extrae los nombres de tabla que la consulta toca. */
    public static function tablasReferenciadas(string $sql, string $verbo): array
    {
        $tablas = [];

        // Nombres declarados en CTEs: son alias temporales, no tablas reales
        $cte = [];
        if (preg_match_all('/\b([a-z_][a-z0-9_]*)\s+AS\s*\(/i', $sql, $m)) {
            $cte = array_map('strtolower', $m[1]);
        }

        $patrones = [
            '/\bFROM\s+`?([a-z_][a-z0-9_]*)`?/i',
            '/\bJOIN\s+`?([a-z_][a-z0-9_]*)`?/i',
            '/\bUPDATE\s+`?([a-z_][a-z0-9_]*)`?/i',
            '/\bINSERT\s+INTO\s+`?([a-z_][a-z0-9_]*)`?/i',
        ];

        foreach ($patrones as $p) {
            if (preg_match_all($p, $sql, $m)) {
                foreach ($m[1] as $t) {
                    $t = strtolower($t);
                    if (in_array($t, $cte, true)) continue;  // es una CTE, no una tabla
                    $tablas[] = $t;
                }
            }
        }

        return array_values(array_unique($tablas));
    }

    /**
     * Comprueba que un INSERT sobre una tabla multiempresa incluya empresa_id
     * entre sus columnas.
     */
    public static function revisarInsert(string $sql): ?string
    {
        $limpio = self::normalizar($sql);
        if (!preg_match('/^INSERT\s+INTO\s+`?([a-z_][a-z0-9_]*)`?\s*\(([^)]*)\)/i', $limpio, $m)) {
            return null;
        }
        $tabla = strtolower($m[1]);
        if (!in_array($tabla, self::tablasTenant(), true)) return null;

        $columnas = array_map(fn($c) => strtolower(trim($c, " `\t\n")), explode(',', $m[2]));
        if (in_array('empresa_id', $columnas, true)) return null;

        return "INSERT en {$tabla} sin columna empresa_id";
    }
}
