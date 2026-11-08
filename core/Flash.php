<?php
class Flash {
    private static function set(string $type, string $msg): void {
        $_SESSION['flash'][] = compact('type', 'msg');
    }

    public static function success(string $msg): void { self::set('success', $msg); }
    public static function error(string $msg): void   { self::set('danger',  $msg); }
    public static function warning(string $msg): void { self::set('warning', $msg); }
    public static function info(string $msg): void    { self::set('info',    $msg); }

    public static function render(): string {
        if (empty($_SESSION['flash'])) return '';
        $icons = ['success' => 'check-circle', 'danger' => 'times-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];
        $html = '';
        foreach ($_SESSION['flash'] as $f) {
            $t    = h($f['type']);
            $icon = $icons[$f['type']] ?? 'info-circle';
            $html .= "<div class=\"alert alert-{$t} alert-dismissible fade show\" role=\"alert\">"
                   . "<i class=\"fas fa-{$icon} me-2\"></i>" . h($f['msg'])
                   . "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>\n";
        }
        unset($_SESSION['flash']);
        return $html;
    }
}
