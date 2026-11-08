<?php
declare(strict_types=1);

namespace TriTransportes;

/**
 * Contenedor de inyección de dependencias mínimo.
 * Cada servicio se registra con una fábrica y se crea una sola vez (singleton por contenedor).
 * Las pruebas reemplazan piezas (por ejemplo, la conexión PDO) con instance().
 */
final class Container
{
    /** @var array<string, callable(Container): mixed> */
    private array $fabricas = [];
    /** @var array<string, mixed> */
    private array $instancias = [];

    public function set(string $id, callable $fabrica): void
    {
        $this->fabricas[$id] = $fabrica;
        unset($this->instancias[$id]);
    }

    public function instance(string $id, mixed $objeto): void
    {
        $this->instancias[$id] = $objeto;
    }

    public function has(string $id): bool
    {
        return isset($this->instancias[$id]) || isset($this->fabricas[$id]);
    }

    /**
     * @template T
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instancias)) {
            return $this->instancias[$id];
        }
        if (!isset($this->fabricas[$id])) {
            throw new \LogicException("El servicio '$id' no está registrado en el contenedor.");
        }
        return $this->instancias[$id] = ($this->fabricas[$id])($this);
    }
}
