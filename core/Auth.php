<?php
class Auth {

    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    public static function require(): void {
        if (!self::check()) {
            redirect('/login');
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::logout();
            redirect('/login?msg=timeout');
        }
        $_SESSION['last_activity'] = time();
    }

    public static function requireAdmin(): void {
        self::require();
        if (!self::isAdmin()) {
            http_response_code(403);
            include ROOT . '/includes/403.php';
            exit;
        }
    }

    public static function requirePermiso(string $modulo, string $accion = 'ver'): void {
        self::require();
        if (!self::can($modulo, $accion)) {
            http_response_code(403);
            include ROOT . '/includes/403.php';
            exit;
        }
    }

    // Permite acceso si el usuario tiene CUALQUIER permiso sobre el módulo (ver, crear, editar, eliminar)
    public static function requireAnyPermiso(string $modulo): void {
        self::require();
        if (!self::canAny($modulo)) {
            http_response_code(403);
            include ROOT . '/includes/403.php';
            exit;
        }
    }

    public static function can(string $modulo, string $accion = 'ver'): bool {
        if (!self::check()) return false;
        if (self::isAdmin()) return true;
        return in_array("{$modulo}:{$accion}", $_SESSION['permisos'] ?? [], true);
    }

    public static function canAny(string $modulo): bool {
        if (!self::check()) return false;
        if (self::isAdmin()) return true;
        foreach ($_SESSION['permisos'] ?? [] as $p) {
            if (str_starts_with($p, "{$modulo}:")) return true;
        }
        return false;
    }

    public static function isAdmin(): bool {
        return ($_SESSION['rol_nombre'] ?? '') === 'Administrador General';
    }

    public static function user(): array {
        return $_SESSION['user'] ?? [];
    }

    public static function id(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    // Empresa ACTIVA (la que se está visualizando). Puede conmutarse.
    public static function empresaId(): int {
        return (int)($_SESSION['empresa_id'] ?? 0);
    }

    // Empresa de origen del usuario (a la que pertenece su cuenta).
    public static function homeEmpresaId(): int {
        return (int)($_SESSION['home_empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
    }

    // Nombre de la empresa activa (para mostrar en cabecera).
    public static function empresaNombre(): string {
        return $_SESSION['empresa_nombre'] ?? ($_SESSION['user']['empresa_nombre'] ?? '');
    }

    // ¿Puede el usuario cambiar de empresa? Solo el Administrador General.
    public static function canSwitchEmpresa(): bool {
        return self::isAdmin();
    }

    // Lista de empresas activas disponibles para conmutar.
    public static function empresasDisponibles(): array {
        if (!self::check()) return [];
        if (self::canSwitchEmpresa()) {
            return DB::all("SELECT id, razon_social FROM empresas WHERE activo = 1 ORDER BY razon_social");
        }
        // Usuario normal: solo su propia empresa.
        return DB::all("SELECT id, razon_social FROM empresas WHERE id = ? AND activo = 1", [self::homeEmpresaId()]);
    }

    // Conmuta la empresa activa, validando permisos y que la empresa exista/esté activa.
    public static function setActiveEmpresa(int $empresaId): bool {
        if (!self::check()) return false;
        // Un usuario no-admin solo puede operar en su propia empresa.
        if (!self::canSwitchEmpresa() && $empresaId !== self::homeEmpresaId()) {
            return false;
        }
        $e = DB::one("SELECT id, razon_social FROM empresas WHERE id = ? AND activo = 1", [$empresaId]);
        if (!$e) return false;
        $_SESSION['empresa_id']     = (int)$e['id'];
        $_SESSION['empresa_nombre'] = $e['razon_social'];
        self::audit('cambiar_empresa', 'empresas', (int)$e['id']);
        return true;
    }

    public static function rolNombre(): string {
        return $_SESSION['rol_nombre'] ?? '';
    }

    // ── Anti fuerza-bruta ────────────────────────────────────────────────────
    private static function ensureThrottleTable(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            DB::run("CREATE TABLE IF NOT EXISTS login_intentos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                ip VARCHAR(45) NOT NULL,
                exito TINYINT(1) NOT NULL DEFAULT 0,
                fecha DATETIME NOT NULL,
                INDEX idx_email_fecha (email, fecha),
                INDEX idx_ip_fecha (ip, fecha)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable) {}
    }

    public static function loginBloqueado(string $email, string $ip): bool {
        self::ensureThrottleTable();
        try {
            $n = DB::count(
                "SELECT COUNT(*) FROM login_intentos
                 WHERE exito = 0 AND fecha > (NOW() - INTERVAL ? MINUTE) AND (email = ? OR ip = ?)",
                [LOGIN_LOCKOUT_MIN, strtolower(trim($email)), $ip]
            );
            return $n >= LOGIN_MAX_INTENTOS;
        } catch (Throwable) {
            return false; // si la tabla falla, no bloquear el acceso legítimo
        }
    }

    private static function registrarIntento(string $email, string $ip, bool $exito): void {
        self::ensureThrottleTable();
        try {
            DB::run(
                "INSERT INTO login_intentos (email, ip, exito, fecha) VALUES (?, ?, ?, NOW())",
                [strtolower(trim($email)), $ip, $exito ? 1 : 0]
            );
        } catch (Throwable) {}
    }

    public static function login(string $email, string $password): bool|string {
        $ip = clientIp();
        if (self::loginBloqueado($email, $ip)) {
            return 'Demasiados intentos fallidos. Por seguridad, espere unos minutos e intente nuevamente.';
        }

        $u = DB::one(
            "SELECT u.*, r.nombre AS rol_nombre, e.razon_social AS empresa_nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             INNER JOIN empresas e ON e.id = u.empresa_id
             WHERE u.email = ? AND u.activo = 1
             LIMIT 1",
            [strtolower(trim($email))]
        );

        if (!$u || !password_verify($password, $u['password_hash'])) {
            self::registrarIntento($email, $ip, false);
            return 'Credenciales incorrectas. Verifique su email y contraseña.';
        }
        self::registrarIntento($email, $ip, true);

        $perms = DB::all(
            "SELECT CONCAT(modulo, ':', accion) AS p FROM permisos WHERE rol_id = ? AND permitido = 1",
            [$u['rol_id']]
        );

        session_regenerate_id(true);
        $_SESSION['user_id']           = (int)$u['id'];
        $_SESSION['empresa_id']        = (int)$u['empresa_id'];   // empresa ACTIVA (conmutable)
        $_SESSION['home_empresa_id']   = (int)$u['empresa_id'];   // empresa de origen del usuario
        $_SESSION['empresa_nombre']    = $u['empresa_nombre'];    // nombre de la empresa activa
        $_SESSION['rol_id']        = (int)$u['rol_id'];
        $_SESSION['rol_nombre']    = $u['rol_nombre'];
        $_SESSION['user']          = [
            'id'             => (int)$u['id'],
            'nombre'         => $u['nombre'],
            'email'          => $u['email'],
            'rol_nombre'     => $u['rol_nombre'],
            'empresa_nombre' => $u['empresa_nombre'],
        ];
        $_SESSION['permisos']      = array_column($perms, 'p');
        $_SESSION['last_activity'] = time();

        self::audit('login', 'usuarios', (int)$u['id']);
        return true;
    }

    public static function logout(): void {
        if (self::check()) self::audit('logout', 'usuarios', self::id());
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function audit(
        string $accion,
        string $modulo,
        int    $entidad_id = 0,
        ?array $antes      = null,
        ?array $despues    = null
    ): void {
        try {
            DB::run(
                "INSERT INTO auditoria
                 (empresa_id, usuario_id, modulo, accion, entidad_tipo, entidad_id, datos_antes, datos_despues, ip, fecha)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    self::empresaId(),
                    self::id(),
                    $modulo,
                    $accion,
                    $modulo,
                    $entidad_id,
                    $antes   ? json_encode($antes,   JSON_UNESCAPED_UNICODE)  : null,
                    $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                    clientIp(),
                ]
            );
        } catch (Throwable) {
            // La falla de auditoría nunca debe romper la aplicación
        }
    }
}
