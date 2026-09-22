<?php

declare(strict_types=1);

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\StageGateItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProjectService
{
    /**
     * Create a project (quota-checked by the model) with the default stage-gate checklists.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $createdBy): Project
    {
        return DB::transaction(function () use ($attributes, $createdBy): Project {
            if (empty($attributes['code'])) {
                $attributes['code'] = $this->nextCode();
            }

            $project = new Project($attributes);
            $project->created_by = $createdBy->id;
            $project->save();

            $this->seedGateItems($project);

            return $project;
        });
    }

    /**
     * Next sequential project code for the current company, e.g. PRJ-0007.
     */
    public function nextCode(): string
    {
        $codes = Project::withTrashed()->where('code', 'like', 'PRJ-%')->pluck('code');
        $max = $codes->map(static fn (string $code): int => (int) substr($code, 4))->max() ?? 0;

        return sprintf('PRJ-%04d', $max + 1);
    }

    /**
     * Create the default checklist items for every stage.
     */
    public function seedGateItems(Project $project): void
    {
        /** @var array<string, list<array{title: string, required: bool}>> $templates */
        $templates = config('stage_gates');

        foreach (ProjectStage::cases() as $stage) {
            foreach ($templates[$stage->value] ?? [] as $i => $item) {
                StageGateItem::query()->create([
                    'project_id' => $project->id,
                    'stage' => $stage,
                    'title' => $item['title'],
                    'is_required' => $item['required'],
                    'sort' => $i,
                ]);
            }
        }
    }
}
