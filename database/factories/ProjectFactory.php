<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Platform\Enums\Province;
use App\Domains\Projects\Enums\DevelopmentType;
use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Projects are company-owned: create them inside a company context
 * (CurrentCompany::runFor) or pass company_id explicitly.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PRJ-####'),
            'name' => fake()->streetName().' '.fake()->randomElement(['Estate', 'Village', 'Heights', 'Residences', 'Park']),
            'development_type' => fake()->randomElement(DevelopmentType::cases()),
            'stage' => ProjectStage::Plan,
            'status' => ProjectStatus::Active,
            'province' => fake()->randomElement(Province::cases()),
            'town' => fake()->city(),
            'estimated_value' => fake()->numberBetween(5, 250) * 1_000_000,
        ];
    }

    public function inStage(ProjectStage $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }
}
