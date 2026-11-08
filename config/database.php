<?php
class DB {
    private static ?PDO $pdo = null;

    public static function conn(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $p = []): PDOStatement {
        // Control de aislamiento multiempresa (ver core/Tenant.php)
        if (class_exists('Tenant')) {
            Tenant::verificar($sql);
        }
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($p);
        return $stmt;
    }

    public static function all(string $sql, array $p = []): array {
        return self::run($sql, $p)->fetchAll();
    }

    public static function one(string $sql, array $p = []): array|false {
        return self::run($sql, $p)->fetch();
    }

    public static function lastId(): string {
        return self::conn()->lastInsertId();
    }

    public static function count(string $sql, array $p = []): int {
        return (int) self::run($sql, $p)->fetchColumn();
    }
}
