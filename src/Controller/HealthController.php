<?php
declare(strict_types=1);

namespace TriTransportes\Controller;

use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Infrastructure\Database;

/** Estado del servicio para los health checks de Docker y del pipeline (público, sin datos sensibles). */
final class HealthController
{
    public function __construct(private readonly Database $db)
    {
    }

    public function health(Request $r): Response
    {
        try {
            $this->db->pdo->query('SELECT 1')->fetchColumn();
            $migraciones = (int) $this->db->pdo->query('SELECT COUNT(*) FROM phinxlog')->fetchColumn();
            return Response::json(['status' => 'ok', 'db' => 'ok', 'migraciones' => $migraciones]);
        } catch (\Throwable) {
            return Response::json(['status' => 'error', 'db' => 'sin conexión'], 503);
        }
    }
}
