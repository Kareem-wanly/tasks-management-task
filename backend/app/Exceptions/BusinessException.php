<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A valid request that breaks a business rule. Rendered once, in
 * bootstrap/app.php, as JSON: {...extra, message, errors?} with status().
 */
abstract class BusinessException extends RuntimeException
{
    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    /** What the caller is told; getMessage() may carry more detail for the log. */
    public function publicMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * Field errors, as a validation error would carry them.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return [];
    }

    /**
     * Further keys of the JSON body (e.g. 'success' => false).
     *
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $errors = $this->errors();

        return [...$this->extra(), 'message' => $this->publicMessage(), ...($errors !== [] ? ['errors' => $errors] : [])];
    }
}
