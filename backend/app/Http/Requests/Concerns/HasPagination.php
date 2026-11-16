<?php

namespace App\Http\Requests\Concerns;

/**
 * For list requests: per_page must be a whole number, and is kept between
 * 1 and 100. Add paginationRules() to rules() and paginationMessages() to messages().
 */
trait HasPagination
{
    protected function paginationRules(): array
    {
        return ['per_page' => ['sometimes', 'integer']];
    }

    protected function paginationMessages(): array
    {
        return ['per_page.integer' => 'The per_page parameter must be an integer.'];
    }

    public function perPage(int $default = 15): int
    {
        return min(max((int) $this->query('per_page', $default), 1), 100);
    }
}
