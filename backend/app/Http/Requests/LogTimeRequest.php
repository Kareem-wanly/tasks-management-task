<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasTimeEntryInput;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** Logs time on a task by hand. */
final class LogTimeRequest extends FormRequest
{
    use HasTimeEntryInput;
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('logTime', $this->routeModel('task', Task::class));
    }
}
