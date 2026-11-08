<?php
declare(strict_types=1);

namespace TriTransportes\Controller;

use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Service\IaService;
use TriTransportes\Validation\Validator;

final class IaController
{
    public function __construct(private readonly IaService $ia)
    {
    }

    public function resumen(Request $r): Response
    {
        $d = Validator::check($r->json(), [
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date'],
            'enfoque' => ['in:general,combustible,flota'],
        ]);
        return Response::json($this->ia->resumen($r->user(), $d['desde'], $d['hasta'], $d['enfoque'] ?? 'general', $r->ip));
    }
}
