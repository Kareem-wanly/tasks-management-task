<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\BitrixSyncCursor;
use Illuminate\Foundation\Http\FormRequest;

/** Step 5.2: Queues a dead letter's work again. Same permission as the status page. */
final class RetryBitrixSyncFailureRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', BitrixSyncCursor::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
