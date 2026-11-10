<?php

namespace App\Http\Controllers;

use App\Models\TaskLookup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD shared by /task-statuses and /task-priorities. Values from Bitrix
 * (is_system) keep their key and Bitrix link; only our own values can be deleted.
 */
abstract class TaskLookupController extends Controller
{
    use AuthorizesRequests;

    /** @return class-string<TaskLookup> */
    abstract protected function model(): string;

    /** Extra validation rules of one lookup type (e.g. is_final for statuses). */
    protected function extraRules(?TaskLookup $lookup): array
    {
        return [];
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->model()::ordered()->get(),
        ]);
    }

    protected function storeLookup(Request $request): JsonResponse
    {
        $this->authorize('create', $this->model());

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique((new ($this->model()))->getTable(), 'key')],
            ...$this->rules(null),
        ]);

        $lookup = $this->model()::create($validated + ['sort' => $this->model()::max('sort') + 1]);

        return response()->json(['data' => $lookup->fresh()], 201);
    }

    protected function updateLookup(Request $request, TaskLookup $lookup): JsonResponse
    {
        $this->authorize('update', $lookup);

        $rules = $this->rules($lookup);
        if ($lookup->is_system) {
            // The Bitrix link of a Bitrix value is fixed; the sync command owns it
            unset($rules['bitrix_id']);
        }

        $lookup->update($request->validate(array_map(fn ($r) => ['sometimes', ...$r], $rules)));

        return response()->json(['data' => $lookup->fresh()]);
    }

    protected function destroyLookup(TaskLookup $lookup): JsonResponse
    {
        $this->authorize('delete', $lookup);

        if ($lookup->is_system) {
            return response()->json(['message' => 'Values that come from Bitrix24 cannot be deleted.'], 422);
        }

        if ($lookup->isUsed()) {
            return response()->json(['message' => 'This value is used by tasks and cannot be deleted.'], 422);
        }

        $lookup->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    private function rules(?TaskLookup $lookup): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(TaskLookup::COLORS)],
            'sort' => ['integer', 'min:0', 'max:65535'],
            // Our own values say which Bitrix value they are sent as
            'bitrix_id' => ['nullable', 'integer', Rule::in(
                $this->model()::where('is_system', true)->whereNotNull('bitrix_id')->pluck('bitrix_id')->all()
            )],
            ...$this->extraRules($lookup),
        ];
    }
}
