<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Project\AddProjectMemberDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            // Only active accounts; the add is written to Bitrix with the service account (SEC-2).
            // Users who aren't in Bitrix are refused by BitrixProjectPusher before any call.
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'role' => ['sometimes', 'string', 'in:member,moderator'],
        ];
    }

    public function toDto(): AddProjectMemberDTO
    {
        /** @var array{user_id: int|string, role?: string} $validated */
        $validated = $this->validated();

        return AddProjectMemberDTO::fromArray($validated);
    }
}
