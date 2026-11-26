<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\BitrixSyncCursor;
use Illuminate\Foundation\Http\FormRequest;

/** Step 5.2: Lists the open dead letters on the sync status page. Same permission as the status page. */
final class ListBitrixSyncFailuresRequest extends FormRequest
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
