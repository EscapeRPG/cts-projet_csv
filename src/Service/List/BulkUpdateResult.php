<?php

namespace App\Service\List;

final readonly class BulkUpdateResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $changedCount,
        public array $errors,
    ) {
    }
}
