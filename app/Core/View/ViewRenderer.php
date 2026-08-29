<?php

declare(strict_types=1);

namespace ReaCms\Core\View;

use InvalidArgumentException;
use Throwable;

final class ViewRenderer
{
    public function __construct(private readonly string $viewPath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        if (preg_match('/^[A-Za-z0-9_\/-]+$/', $view) !== 1) {
            throw new InvalidArgumentException('The view name is invalid.');
        }

        $file = rtrim($this->viewPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $view
            . '.php';

        if (!is_file($file)) {
            throw new InvalidArgumentException(sprintf('View "%s" does not exist.', $view));
        }

        $escape = static fn (mixed $value): string => htmlspecialchars(
            is_scalar($value) ? (string) $value : '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
