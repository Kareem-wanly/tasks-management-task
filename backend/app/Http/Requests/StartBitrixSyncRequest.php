<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Bitrix\StartBitrixSyncDTO;
use App\Enums\BitrixSyncMode;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\BitrixSyncCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Step 5.1: the "sync now" buttons. */
final class StartBitrixSyncRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', BitrixSyncCursor::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['mode' => ['required', 'string', Rule::enum(BitrixSyncMode::class)]];
    }

    public function toDto(): StartBitrixSyncDTO
    {
        /** @var array{mode: string} $validated */
        $validated = $this->validated();

        return StartBitrixSyncDTO::fromArray($validated);
    }
}
