<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Requests;

use App\Domains\Projects\Enums\ProjectStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-projects') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'stage' => ['nullable', Rule::enum(ProjectStage::class)],
            'planned_date' => [$creating ? 'required' : 'sometimes', 'date'],
            'forecast_date' => ['nullable', 'date'],
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
