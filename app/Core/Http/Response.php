<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

use JsonException;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws JsonException
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->body, $this->status, [...$this->headers, $name => $value]);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp($header, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): never
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->body;
        exit;
    }
}
