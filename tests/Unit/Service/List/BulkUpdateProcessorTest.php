<?php

namespace App\Tests\Unit\Service\List;

use App\Service\List\BulkUpdateProcessor;
use PHPUnit\Framework\TestCase;

final class BulkUpdateProcessorTest extends TestCase
{
    public function testItUpdatesOnlyAllowedEntitiesAndCollectsLabelledErrors(): void
    {
        $first = new class(1, 'Premier') {
            public function __construct(public int $id, public string $name)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }
        };
        $second = new class(2, 'Second') {
            public function __construct(public int $id, public string $name)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }
        };

        $result = (new BulkUpdateProcessor())->process(
            [$first, $second],
            [1 => ['name' => 'Modifié'], 2 => ['name' => ''], 3 => ['name' => 'Interdit'], 4 => 'invalide'],
            static function (object $entity, array $fields): array {
                if ($fields['name'] === '') {
                    return ['Nom requis.'];
                }

                $entity->name = $fields['name'];

                return [];
            },
            static fn(object $entity): string => $entity->name,
        );

        self::assertSame(1, $result->changedCount);
        self::assertSame(['[Second] Nom requis.'], $result->errors);
        self::assertSame('Modifié', $first->name);
        self::assertSame('Second', $second->name);
    }
}
