<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;

/** Lists every permission. */
final class ListPermissionsRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Permission::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
