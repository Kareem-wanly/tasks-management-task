<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasTimeEntryInput;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskElapsedItem;
use Illuminate\Foundation\Http\FormRequest;

/** Changes one's own time entry. */
final class UpdateTimeEntryRequest extends FormRequest
{
    use HasTimeEntryInput;
    use ResolvesActor;

    public function authorize(): bool
    {
        $entry = $this->routeModel('entry', TaskElapsedItem::class);

        return $this->actor()->can('manageTimeEntry', [$entry->task, $entry]);
    }
}
