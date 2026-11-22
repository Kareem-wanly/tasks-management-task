<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskElapsedItem;
use Illuminate\Foundation\Http\FormRequest;

/** Deletes one's own time entry. */
final class DeleteTimeEntryRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('manageTimeEntry', [$this->routeModel('entry', TaskElapsedItem::class)->task, $this->routeModel('entry', TaskElapsedItem::class)]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
