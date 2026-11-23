<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Bitrix\BitrixEventDTO;
use App\Services\Contracts\BitrixEventServiceInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * An event from Bitrix24 (outside auth:sanctum). Only events signed with our
 * portal's application token get through.
 */
final class BitrixEventRequest extends FormRequest
{
    public function authorize(BitrixEventServiceInterface $events): bool
    {
        return $events->isFromOurPortal($this->input('auth.application_token'), $this->input('auth.member_id'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function toDto(): BitrixEventDTO
    {
        /** @var array{event?: ?string, data?: array<string, mixed>|null} $validated */
        $validated = $this->validated();

        return BitrixEventDTO::fromArray($validated);
    }

    /** Bitrix expects 401 for a bad signature, not 403. */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Unknown application token.'], 401));
    }
}
