<?php
class CSRF {
    public static function token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    public static function verify(): bool {
        return isset($_POST['csrf_token'])
            && hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    }

    public static function require(): void {
        if (isPost() && !self::verify()) {
            http_response_code(403);
            die('Error de seguridad: token CSRF inválido. Recargue la página e intente nuevamente.');
        }
    }
}
