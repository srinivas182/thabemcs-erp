<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search (Ctrl+K). Results respect company isolation and the user's permissions.
 *
 * Uses indexed database lookups for now; moves to Meilisearch (Laravel Scout)
 * once document and record volumes grow (see ADR-0004).
 */
final class SearchController
{
    private const int LIMIT = 6;

    public function __invoke(Request $request, CurrentCompany $context): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        /** @var User $user */
        $user = $request->user();
        $like = '%'.addcslashes($term, '%_\\').'%';
        $results = [];

        foreach (Project::query()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->orderBy('name')->limit(self::LIMIT)->get() as $project) {
            $results[] = [
                'type' => 'project',
                'title' => $project->name,
                'subtitle' => "{$project->code}, {$project->stage->label()}",
                'url' => null,
            ];
        }

        $company = $context->get();
        if ($company !== null && $user->can('manage-company-users')) {
            foreach (User::query()->where('company_id', $company->getKey())
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')->limit(self::LIMIT)->get() as $person) {
                $results[] = [
                    'type' => 'person',
                    'title' => $person->name,
                    'subtitle' => $person->job_title ?? $person->email,
                    'url' => route('settings.users.index'),
                ];
            }
        }

        if ($user->is_super_admin) {
            foreach (Company::query()->where('name', 'like', $like)->orderBy('name')->limit(self::LIMIT)->get() as $match) {
                $results[] = [
                    'type' => 'company',
                    'title' => $match->name,
                    'subtitle' => $match->legal_name ?? 'Company',
                    'url' => route('platform.companies.edit', $match),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
