<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Platform\Enums\CompanyStatus;
use App\Domains\Platform\Enums\Module;
use App\Domains\Platform\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'legal_name' => $name.' (Pty) Ltd',
            'registration_number' => fake()->numerify('20##/######/07'),
            'vat_number' => fake()->numerify('4#########'),
            'status' => CompanyStatus::Active,
            'max_projects' => null,
            'max_users' => null,
            'max_storage_mb' => null,
            'modules' => Module::values(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => CompanyStatus::Suspended]);
    }

    /**
     * @param  list<Module>  $modules
     */
    public function withModules(array $modules): static
    {
        return $this->state(fn () => ['modules' => array_map(static fn (Module $m): string => $m->value, $modules)]);
    }

    public function withProjectLimit(?int $limit): static
    {
        return $this->state(fn () => ['max_projects' => $limit]);
    }
}
