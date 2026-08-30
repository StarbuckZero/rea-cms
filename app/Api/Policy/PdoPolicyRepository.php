<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

use PDO;
use RuntimeException;

final class PdoPolicyRepository implements PolicyRepository
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->table = $prefix . 'api_policies';
    }

    public function forResource(string $resource, string $operation): PolicySet
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT policy FROM `%s` WHERE '
            . '(layer = :global_layer AND resource = :wildcard_resource AND operation = :wildcard_operation) '
            . 'OR (resource = :resource AND operation IN (:operation, :resource_wildcard))',
            $this->table,
        ));
        $statement->execute([
            'global_layer' => 'global',
            'wildcard_resource' => '*',
            'wildcard_operation' => '*',
            'resource' => $resource,
            'operation' => $operation,
            'resource_wildcard' => '*',
        ]);
        $policies = array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));

        return $policies === [] ? new PolicySet(['disabled']) : new PolicySet($policies);
    }
}
