<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Platform\Enums\Module;
use App\Domains\Platform\Enums\Province;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Models\Region;
use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\StageGateItem;
use App\Domains\Projects\Services\ProjectService;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Local-only demo data: one company, two regions, a company admin and sample projects.
 */
class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['name' => 'Steve Maqueens Developments'],
            [
                'legal_name' => 'Steve Maqueens (Pty) Ltd',
                'modules' => Module::values(),
                'max_projects' => 500,
                'max_users' => 250,
            ],
        );

        app(CurrentCompany::class)->runFor($company, function () use ($company): void {
            $gauteng = Region::query()->firstOrCreate(['name' => 'Gauteng'], ['province' => Province::Gauteng]);
            Region::query()->firstOrCreate(['name' => 'KwaZulu-Natal'], ['province' => Province::KwaZuluNatal]);

            $admin = User::query()->firstOrCreate(
                ['email' => 'companyadmin@thabekhulu.local'],
                ['name' => 'Senzo Shange', 'password' => 'password', 'job_title' => 'Facilitator'],
            );
            $admin->forceFill(['company_id' => $company->getKey(), 'email_verified_at' => now()])->save();

            setPermissionsTeamId($company->getKey());
            $admin->assignRole(Role::CompanyAdmin->value);

            if (Project::query()->doesntExist()) {
                $service = app(ProjectService::class);

                foreach (ProjectStage::cases() as $i => $stage) {
                    Project::factory()->count(max(1, 4 - $i))->inStage($stage)
                        ->create(['region_id' => $gauteng->getKey(), 'project_manager_id' => $admin->id])
                        ->each(function (Project $project) use ($service, $admin): void {
                            $service->seedGateItems($project);

                            // Earlier stages are complete, as they would be for a project that has progressed.
                            StageGateItem::query()->where('project_id', $project->id)
                                ->whereIn('stage', array_map(fn (ProjectStage $s) => $s->value, array_slice(ProjectStage::cases(), 0, $project->stage->position() - 1)))
                                ->update(['completed_at' => now(), 'completed_by' => $admin->id]);
                        });
                }
            }
        });
    }
}
