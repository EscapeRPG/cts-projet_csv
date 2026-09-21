<?php

namespace App\Service\List;

final class BulkUpdateProcessor
{
    /**
     * @template TEntity of object
     * @param iterable<TEntity> $entities Entities the current user is allowed to update.
     * @param array<array-key, mixed> $payload
     * @param callable(TEntity, array<array-key, mixed>): list<string> $update
     * @param callable(TEntity): string $label
     */
    public function process(
        iterable $entities,
        array $payload,
        callable $update,
        callable $label,
    ): BulkUpdateResult {
        $allowedById = [];
        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getId')) {
                throw new \LogicException(sprintf('Expected entity with getId(), got %s.', get_debug_type($entity)));
            }

            $id = $entity->getId();
            if ($id !== null) {
                $allowedById[(string) $id] = $entity;
            }
        }

        $changedCount = 0;
        $errors = [];

        foreach ($payload as $id => $fields) {
            $id = trim((string) $id);
            if ($id === '' || !isset($allowedById[$id]) || !is_array($fields)) {
                continue;
            }

            $entity = $allowedById[$id];
            $rowErrors = $update($entity, $fields);
            if ($rowErrors !== []) {
                $errors[] = sprintf('[%s] %s', $label($entity), implode(' | ', $rowErrors));
                continue;
            }

            ++$changedCount;
        }

        return new BulkUpdateResult($changedCount, $errors);
    }
}
