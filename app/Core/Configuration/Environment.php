<?php

declare(strict_types=1);

namespace ReaCms\Core\Configuration;

use Dotenv\Dotenv;
use InvalidArgumentException;
use RuntimeException;

final class Environment
{
    /**
     * @param array<string, string> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    public static function load(string $projectRoot): self
    {
        if (!is_dir($projectRoot)) {
            throw new InvalidArgumentException('The project root must be an existing directory.');
        }

        Dotenv::createImmutable($projectRoot)->safeLoad();

        return self::fromArray($_ENV);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        return new self($normalized);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function require(string $key): string
    {
        $value = $this->get($key);

        if ($value === null || trim($value) === '') {
            throw new RuntimeException(sprintf('Required environment value "%s" is missing.', $key));
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($parsed === null) {
            throw new RuntimeException(sprintf('Environment value "%s" must be a boolean.', $key));
        }

        return $parsed;
    }
}
