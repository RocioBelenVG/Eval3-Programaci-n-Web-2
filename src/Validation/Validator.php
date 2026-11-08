<?php
declare(strict_types=1);

namespace TriTransportes\Validation;

use TriTransportes\Exception\ApiException;

/**
 * Validación del segundo nivel (servidor). Devuelve solo los campos declarados,
 * ya convertidos a su tipo, y junta todos los errores en un único 422.
 *
 * Reglas: required, nullable, int, numeric, string, bool, email, date, time, rut,
 *         min:N, max:N, gt:N (para números: valor; para texto: largo), in:a,b,c, regex:/.../
 *
 *   $datos = Validator::check($body, [
 *       'vehiculo_id' => ['required', 'int', 'min:1'],
 *       'fecha'       => ['required', 'date'],
 *   ]);
 */
final class Validator
{
    /**
     * @param array<string, mixed>        $input
     * @param array<string, list<string>> $rules
     * @return array<string, mixed>
     */
    public static function check(array $input, array $rules): array
    {
        $out = [];
        $errores = [];
        foreach ($rules as $campo => $reglas) {
            $presente = array_key_exists($campo, $input) && $input[$campo] !== '' && $input[$campo] !== null;
            if (!$presente) {
                if (in_array('required', $reglas, true)) {
                    $errores[] = ['field' => $campo, 'message' => 'Este campo es obligatorio.'];
                } elseif (array_key_exists($campo, $input)) {
                    $out[$campo] = null; // enviado vacío: se interpreta como "sin valor"
                }
                continue;
            }
            try {
                $out[$campo] = self::aplicar($input[$campo], $reglas);
            } catch (\DomainException $e) {
                $errores[] = ['field' => $campo, 'message' => $e->getMessage()];
            }
        }
        if ($errores !== []) {
            throw ApiException::validation($errores);
        }
        return $out;
    }

    /** @param list<string> $reglas */
    private static function aplicar(mixed $v, array $reglas): mixed
    {
        $tipo = 'string';
        foreach ($reglas as $regla) {
            [$nombre, $arg] = array_pad(explode(':', $regla, 2), 2, null);
            switch ($nombre) {
                case 'required':
                case 'nullable':
                    break;
                case 'int':
                    if (is_int($v) || (is_string($v) && preg_match('/^-?\d{1,10}$/', $v))) {
                        $v = (int) $v;
                    } elseif (is_float($v) && floor($v) === $v) {
                        $v = (int) $v;
                    } else {
                        throw new \DomainException('Debe ser un número entero.');
                    }
                    $tipo = 'num';
                    break;
                case 'numeric':
                    if (!is_int($v) && !is_float($v) && !(is_string($v) && is_numeric($v))) {
                        throw new \DomainException('Debe ser un número.');
                    }
                    $v = $v + 0;
                    $tipo = 'num';
                    break;
                case 'bool':
                    $b = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($b === null) {
                        throw new \DomainException('Debe ser verdadero o falso.');
                    }
                    $v = $b;
                    $tipo = 'bool';
                    break;
                case 'string':
                    if (!is_string($v) && !is_int($v) && !is_float($v)) {
                        throw new \DomainException('Debe ser texto.');
                    }
                    $v = trim((string) $v);
                    break;
                case 'email':
                    if (!is_string($v) || !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        throw new \DomainException('Debe ser un correo electrónico válido.');
                    }
                    $v = strtolower(trim($v));
                    break;
                case 'date':
                    $d = is_string($v) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $v) : false;
                    if ($d === false || $d->format('Y-m-d') !== $v) {
                        throw new \DomainException('Debe ser una fecha válida con formato AAAA-MM-DD.');
                    }
                    break;
                case 'time':
                    if (!is_string($v) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                        throw new \DomainException('Debe ser una hora válida con formato HH:MM.');
                    }
                    break;
                case 'rut':
                    if (!is_string($v) || !self::rutValido($v)) {
                        throw new \DomainException('El RUT no es válido (formato 12345678-9 y dígito verificador correcto).');
                    }
                    $v = strtoupper($v);
                    break;
                case 'min':
                    if ($tipo === 'num' ? $v < (float) $arg : mb_strlen((string) $v) < (int) $arg) {
                        throw new \DomainException($tipo === 'num' ? "Debe ser mayor o igual a $arg." : "Debe tener al menos $arg caracteres.");
                    }
                    break;
                case 'max':
                    if ($tipo === 'num' ? $v > (float) $arg : mb_strlen((string) $v) > (int) $arg) {
                        throw new \DomainException($tipo === 'num' ? "Debe ser menor o igual a $arg." : "Debe tener como máximo $arg caracteres.");
                    }
                    break;
                case 'gt':
                    if ($v <= (float) $arg) {
                        throw new \DomainException("Debe ser mayor que $arg.");
                    }
                    break;
                case 'in':
                    $opciones = explode(',', (string) $arg);
                    if (!in_array((string) $v, $opciones, true)) {
                        throw new \DomainException('Valor no permitido. Opciones: ' . implode(', ', $opciones) . '.');
                    }
                    break;
                case 'regex':
                    if (!preg_match((string) $arg, (string) $v)) {
                        throw new \DomainException('El formato no es válido.');
                    }
                    break;
                default:
                    throw new \LogicException("Regla de validación desconocida: $nombre");
            }
        }
        return $v;
    }

    /** RUT chileno con dígito verificador (módulo 11). */
    public static function rutValido(string $rut): bool
    {
        if (!preg_match('/^(\d{7,8})-([\dkK])$/', $rut, $m)) {
            return false;
        }
        $suma = 0;
        $factor = 2;
        for ($i = strlen($m[1]) - 1; $i >= 0; $i--) {
            $suma += (int) $m[1][$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $dv = 11 - ($suma % 11);
        $esperado = match ($dv) { 11 => '0', 10 => 'K', default => (string) $dv };
        return strtoupper($m[2]) === $esperado;
    }

    /** Página y tamaño de página desde el query string (per_page entre 1 y 100). */
    public static function paginacion(array $query): array
    {
        $d = self::check($query, ['page' => ['int', 'min:1'], 'per_page' => ['int', 'min:1', 'max:100']]);
        return [$d['page'] ?? 1, $d['per_page'] ?? 25];
    }
}
