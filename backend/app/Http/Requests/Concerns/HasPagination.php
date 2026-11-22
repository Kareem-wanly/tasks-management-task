<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * For list requests: per_page must be a whole number between 1 and 100 (SEC-3).
 * Add paginationRules() to rules() and paginationMessages() to messages().
 */
trait HasPagination
{
    private const MAX_PER_PAGE = 100;

    public function perPage(int $default = 15): int
    {
        // Validation already enforces the range; the clamp is a safety net for a request that forgets paginationRules().
        return min(max((int) $this->query('per_page', $default), 1), self::MAX_PER_PAGE);
    }

    /** @return array<string, mixed> */
    protected function paginationRules(): array
    {
        return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE]];
    }

    /** @return array<string, string> */
    protected function paginationMessages(): array
    {
        return [
            'per_page.integer' => 'The per_page parameter must be an integer.',
            'per_page.min' => 'The per_page parameter must be at least 1.',
            'per_page.max' => 'The per_page parameter may not be greater than '.self::MAX_PER_PAGE.'.',
        ];
    }
}
