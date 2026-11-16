<?php

namespace App\Http\Requests;

use App\Services\BitrixEventService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * An event from Bitrix24 (outside auth:sanctum). Only events signed with our
 * portal's application token get through.
 */
class BitrixEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(BitrixEventService::class)->isFromOurPortal($this->input('auth.application_token'), $this->input('auth.member_id'));
    }

    public function rules(): array
    {
        return [
            'event' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ];
    }

    /** Bitrix expects 401 for a bad signature, not 403. */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Unknown application token.'], 401));
    }
}
