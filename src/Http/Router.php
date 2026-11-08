<?php
declare(strict_types=1);

namespace TriTransportes\Http;

use TriTransportes\Domain\Rol;
use TriTransportes\Exception\ApiException;

/**
 * Enrutador por método y patrón (`/viajes/{id}`). Cada ruta declara si es pública
 * y qué roles pueden usarla; la autorización fina (por ejemplo, "solo el conductor
 * asignado") la resuelven los servicios.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, names: list<string>, handler: array{class-string, string}, roles: list<Rol>|null, public: bool}> */
    private array $routes = [];

    /**
     * @param array{class-string, string} $handler
     * @param list<Rol>|null $roles  null = cualquier usuario autenticado
     */
    public function add(string $method, string $pattern, array $handler, ?array $roles = null, bool $public = false): void
    {
        preg_match_all('/\{(\w+)\}/', $pattern, $m);
        // {id} es un entero positivo; cualquier otro parámetro es un identificador corto en minúsculas
        $regex = '#^' . preg_replace_callback('/\{(\w+)\}/', fn ($x) => $x[1] === 'id' ? '([1-9][0-9]{0,9})' : '([a-z][a-z0-9-]{0,40})', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'names' => $m[1], 'handler' => $handler, 'roles' => $roles, 'public' => $public];
    }

    /** @return array{handler: array{class-string, string}, params: array<string, string>, roles: list<Rol>|null, public: bool} */
    public function match(string $method, string $path): array
    {
        $permitidos = [];
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            if ($r['method'] !== $method) {
                $permitidos[] = $r['method'];
                continue;
            }
            array_shift($m);
            return ['handler' => $r['handler'], 'params' => array_combine($r['names'], $m) ?: [], 'roles' => $r['roles'], 'public' => $r['public']];
        }
        if ($permitidos !== []) {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', "Método $method no permitido en esta ruta.", [], ['Allow' => implode(', ', array_unique($permitidos))]);
        }
        throw ApiException::notFound('La ruta solicitada no existe.');
    }
}
