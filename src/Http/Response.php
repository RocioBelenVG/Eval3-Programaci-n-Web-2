<?php
declare(strict_types=1);

namespace TriTransportes\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        return new self($status, $body, ['Content-Type' => 'application/json; charset=utf-8'] + $headers);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    /** Decodifica el cuerpo (útil en pruebas). */
    public function data(): mixed
    {
        return $this->body === '' ? null : json_decode($this->body, true);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        echo $this->body;
    }
}
