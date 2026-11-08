<?php
declare(strict_types=1);

namespace TriTransportes\Controller;

use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Service\AuthService;
use TriTransportes\Validation\Validator;

final class AuthController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function login(Request $r): Response
    {
        $d = Validator::check($r->json(), [
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);
        return Response::json($this->auth->login($d['email'], $d['password'], $r->ip), 200, ['Cache-Control' => 'no-store']);
    }

    public function me(Request $r): Response
    {
        return Response::json($r->user()->toArray());
    }
}
