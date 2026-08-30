<?php

declare(strict_types=1);

namespace ReaCms\Content;

final class RevisionService
{
    /**
     * @param array<string, mixed> $snapshot
     * @param callable(array<string, mixed>): void $store
     */
    public function record(ResourceDefinition $definition, array $snapshot, callable $store): void
    {
        $store((new ContentValidator())->validate($definition, $snapshot));
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $revision
     * @param callable(array<string, mixed>): void $saveCurrentRevision
     * @param callable(array<string, mixed>): void $restore
     */
    public function restore(
        ResourceDefinition $definition,
        array $current,
        array $revision,
        callable $saveCurrentRevision,
        callable $restore,
    ): void {
        $validatedCurrent = (new ContentValidator())->validate($definition, $current);
        $validatedRevision = (new ContentValidator())->validate($definition, $revision);
        $saveCurrentRevision($validatedCurrent);
        $restore($validatedRevision);
    }
}
