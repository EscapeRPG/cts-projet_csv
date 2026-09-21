<?php

namespace App\Tests\Unit\Service\List;

use App\Service\List\ActiveStatusFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ActiveStatusFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, int>, bool, bool}>
     */
    public static function filters(): iterable
    {
        yield 'defaults to active' => [[], true, false];
        yield 'active only' => [['active' => 1, 'inactive' => 0], true, false];
        yield 'inactive only' => [['active' => 0, 'inactive' => 1], false, true];
        yield 'both selected' => [['active' => 1, 'inactive' => 1], true, true];
        yield 'neither selected means all' => [['active' => 0, 'inactive' => 0], true, true];
    }

    #[DataProvider('filters')]
    public function testItResolvesActiveFilters(array $query, bool $active, bool $inactive): void
    {
        $filter = ActiveStatusFilter::fromRequest(new Request($query));

        self::assertSame($active, $filter->includeActive);
        self::assertSame($inactive, $filter->includeInactive);
        self::assertSame(
            ['active' => $active ? 1 : 0, 'inactive' => $inactive ? 1 : 0],
            $filter->queryParameters(),
        );
    }
}
