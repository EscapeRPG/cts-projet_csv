<?php

namespace App\Service\Suivi;

/**
 * Provides simple in-memory array pagination.
 */
final class ArrayPaginator
{
    /**
     * Paginates items and returns sliced data with pagination metadata.
     *
     * @param array<int, mixed> $items Source items.
     * @param int $page Requested page number (1-based).
     * @param int $perPage Number of items per page.
     *
     * @return array{items:array<int,mixed>,pagination:array<string,int|bool>} Paginated result payload.
     */
    public function paginate(array $items, int $page, int $perPage): array
    {
        $totalItems = count($items);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = max(1, min($page, $totalPages));
        $offset = ($currentPage - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'pagination' => [
                'page' => $currentPage,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'has_previous' => $currentPage > 1,
                'has_next' => $currentPage < $totalPages,
                'previous_page' => $currentPage > 1 ? $currentPage - 1 : 1,
                'next_page' => $currentPage < $totalPages ? $currentPage + 1 : $totalPages,
            ],
        ];
    }
}
