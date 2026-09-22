<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Requests;

use App\Domains\Platform\Enums\Province;
use App\Domains\Projects\Enums\DevelopmentType;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProjectRequest extends FormRequest
{
    use ResolvesCompanyUsers;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-projects') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $companyId = app(CurrentCompany::class)->id();

        return [
            'code' => [
                'nullable', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9\-]*$/',
                Rule::unique('projects', 'code')->where('company_id', $companyId)->ignore($project instanceof Project ? $project->id : null),
            ],
            'name' => ['required', 'string', 'max:160'],
            'development_type' => ['required', Rule::enum(DevelopmentType::class)],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'region_id' => ['nullable', Rule::exists('regions', 'id')->where('company_id', $companyId)],
            'province' => ['required', Rule::enum(Province::class)],
            'town' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-35,-22'],
            'longitude' => ['nullable', 'numeric', 'between:16,33'],
            'geofence_radius_m' => ['nullable', 'integer', 'between:50,5000'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_completion_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_manager' => ['nullable', 'string', $this->companyUserRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use capital letters, numbers and dashes only, for example BAL-01.',
            'latitude.between' => 'This location is outside South Africa.',
            'longitude.between' => 'This location is outside South Africa.',
            'planned_completion_date.after_or_equal' => 'Completion must be on or after the start date.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function projectData(): array
    {
        $data = $this->safe()->except(['project_manager']);
        $data['project_manager_id'] = $this->userIdFor('project_manager');
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data;
    }
}
