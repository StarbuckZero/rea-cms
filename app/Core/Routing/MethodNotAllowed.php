<?php

declare(strict_types=1);

namespace ReaCms\Core\Routing;

use RuntimeException;

final class MethodNotAllowed extends RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(private readonly array $allowedMethods)
    {
        parent::__construct('The request method is not allowed for this route.');
    }

    /**
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
