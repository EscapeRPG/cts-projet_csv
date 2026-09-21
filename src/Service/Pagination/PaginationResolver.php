<?php

namespace App\Service\Pagination;

use Symfony\Component\HttpFoundation\Request;

class PaginationResolver
{
    /**
     * @return array{page:int,offset:int,total_pages:int,view:array<string,int|bool>}
     */
    public function computePagination(Request $request, int $perPage, int $totalItems): array
    {
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $page = max(1, min($request->query->getInt('page', 1), $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'offset' => $offset,
            'total_pages' => $totalPages,
            'view' => $this->buildPaginationView($page, $perPage, $totalItems, $totalPages),
        ];
    }

    /**
     * @return array{page:int,per_page:int,total_items:int,total_pages:int,has_previous:bool,has_next:bool,previous_page:int,next_page:int}
     */
    private function buildPaginationView(int $page, int $perPage, int $totalItems, int $totalPages): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages,
            'previous_page' => $page > 1 ? $page - 1 : 1,
            'next_page' => $page < $totalPages ? $page + 1 : $totalPages,
        ];
    }
}
