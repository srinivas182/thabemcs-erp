<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Requests;

use App\Domains\Projects\Enums\TaskPriority;
use App\Domains\Projects\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TaskRequest extends FormRequest
{
    use ResolvesCompanyUsers;

    public function authorize(): bool
    {
        return $this->user() !== null; // Visibility is enforced by company scoping; permissions per action in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assignee' => ['nullable', 'string', $this->companyUserRule()],
            'due_date' => ['nullable', 'date'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function taskData(): array
    {
        $data = $this->safe()->except(['assignee']);
        if ($this->has('assignee')) {
            $data['assignee_id'] = $this->userIdFor('assignee');
        }

        return $data;
    }
}
