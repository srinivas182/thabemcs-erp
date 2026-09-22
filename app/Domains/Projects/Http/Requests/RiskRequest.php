<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Requests;

use App\Domains\Projects\Enums\RiskKind;
use App\Domains\Projects\Enums\RiskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RiskRequest extends FormRequest
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
        $creating = $this->isMethod('post');

        return [
            'kind' => [$creating ? 'required' : 'sometimes', Rule::enum(RiskKind::class)],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'likelihood' => ['sometimes', 'integer', 'between:1,5'],
            'impact' => ['sometimes', 'integer', 'between:1,5'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'owner' => ['nullable', 'string', $this->companyUserRule()],
            'status' => ['sometimes', Rule::enum(RiskStatus::class)],
            'review_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function riskData(): array
    {
        $data = $this->safe()->except(['owner']);
        if ($this->has('owner')) {
            $data['owner_id'] = $this->userIdFor('owner');
        }

        return $data;
    }
}
