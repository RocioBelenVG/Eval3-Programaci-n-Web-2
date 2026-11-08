<?php
function h(mixed $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . APP_URL . $path);
    exit;
}

function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function postInt(string $key, int $default = 0): int {
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}

function postFloat(string $key, float $default = 0.0): float {
    return isset($_POST[$key]) ? (float)str_replace(',', '.', $_POST[$key]) : $default;
}

function get(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function getInt(string $key, int $default = 0): int {
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}

function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function formatDate(?string $d, string $fmt = 'd/m/Y'): string {
    if (!$d) return '—';
    try { return (new DateTime($d))->format($fmt); } catch (Exception) { return $d; }
}

function formatNum(float $n, int $dec = 0): string {
    return number_format($n, $dec, ',', '.');
}

/** Mes y año en castellano: "Julio 2026". */
function mesAnio(?string $fecha = null): string {
    static $meses = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $ts = $fecha ? strtotime($fecha) : time();
    return $meses[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function formatMoney(float $n): string {
    return '$ ' . number_format($n, 0, ',', '.');
}

function paginate(int $total, int $page, int $limit = RECORDS_PER_PAGE): array {
    $pages  = (int)ceil($total / $limit);
    $page   = max(1, min($page, $pages ?: 1));
    $offset = ($page - 1) * $limit;
    return compact('total', 'pages', 'page', 'limit', 'offset');
}

function paginationHtml(array $pag, string $baseUrl): string {
    if ($pag['pages'] <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    $prev = $pag['page'] > 1 ? $pag['page'] - 1 : 1;
    $next = $pag['page'] < $pag['pages'] ? $pag['page'] + 1 : $pag['pages'];
    $html .= '<li class="page-item' . ($pag['page'] == 1 ? ' disabled' : '') . '"><a class="page-link" href="' . $baseUrl . '&page=' . $prev . '">«</a></li>';
    for ($i = max(1, $pag['page'] - 2); $i <= min($pag['pages'], $pag['page'] + 2); $i++) {
        $html .= '<li class="page-item' . ($i == $pag['page'] ? ' active' : '') . '"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($pag['page'] == $pag['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . $baseUrl . '&page=' . $next . '">»</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Botones de exportación de un listado (Excel + PDF).
 * Ambos apuntan a la misma vista de DataViews con los filtros vigentes, así el
 * archivo descargado siempre refleja lo que el usuario está viendo.
 */
function botonesExportacion(string $vista, array $filtros = []): string {
    $qs = ['vista' => $vista];
    foreach ($filtros as $k => $v) {
        if ($v === null || $v === '' || $v === 0 || $v === '0') continue;
        $qs[$k] = $v;
    }
    $query = http_build_query($qs);
    $base  = APP_URL;

    return '<a href="' . $base . '/api/v1/export?' . $query . '" class="btn btn-sm btn-excel">'
         . '<i class="fas fa-file-excel me-1"></i>Excel</a>'
         . '<a href="' . $base . '/api/v1/export_pdf?' . $query . '" class="btn btn-sm btn-pdf" '
         . 'target="_blank" rel="noopener" title="Reporte PDF con los filtros aplicados">'
         . '<i class="fas fa-file-pdf me-1"></i>PDF</a>';
}

function badgeActivo(int $activo): string {
    return $activo
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>';
}

function currentModule(): string {
    $path = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('/modules\/([^\/]+)/', $path, $m)) return $m[1];
    return 'dashboard';
}

// ── Seguridad: utilidades ────────────────────────────────────────────────────
/** IP real del cliente (considera proxys de confianza como Cloudflare). */
function clientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Política de contraseña: mínimo 8, al menos una letra y un número. */
function passwordEsFuerte(string $p): bool {
    return strlen($p) >= 8
        && preg_match('/[A-Za-z]/', $p) === 1
        && preg_match('/\d/', $p) === 1;
}

/** Extensión segura derivada del MIME validado (no del nombre del usuario). */
function uploadExtFromMime(string $mime): ?string {
    static $map = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];
    return $map[$mime] ?? null;
}
