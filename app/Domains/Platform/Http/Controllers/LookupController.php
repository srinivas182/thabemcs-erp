<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Funding\Models\Investor;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workforce\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search-as-you-type options for dropdowns. Pages never receive whole tables: a field asks for at most
 * 20 matches, or resolves the label of one saved value (?key=). Queries use prefix-friendly LIKE on
 * indexed name/code columns and are always inside the current company.
 */
final class LookupController
{
    private const int LIMIT = 20;

    public function __construct(private readonly CurrentCompany $context) {}

    public function __invoke(Request $request, string $type): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $key = $request->string('key')->toString();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        [$query, $keyColumn, $label] = match ($type) {
            'projects' => [
                Project::query()->when($request->query('status') === 'active', fn (Builder $b) => $b->where('status', 'active'))
                    ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                    ->orderBy('code'),
                'ulid', static fn (Project $p): string => "{$p->code} {$p->name}",
            ],
            'suppliers' => [
                Supplier::query()->where('status', 'active')
                    ->when($request->query('types'), fn (Builder $b, $types) => $b->whereIn('type', explode(',', (string) $types)))
                    ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('trading_name', 'like', $like)))
                    ->orderBy('name'),
                'ulid', static fn (Supplier $s): string => $s->name,
            ],
            'people' => [
                User::query()->where('company_id', $this->context->id())->where('is_active', true)
                    ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                    ->orderBy('name'),
                'ulid', static fn (User $u): string => $u->getAttribute('job_title') ? "{$u->name} ({$u->getAttribute('job_title')})" : $u->name,
            ],
            'employees' => [
                Employee::query()->when($request->query('status') !== 'all', fn (Builder $b) => $b->where('status', 'active'))
                    ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('last_name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('employee_number', 'like', $like)))
                    ->orderBy('last_name'),
                'ulid', static fn (Employee $e): string => "{$e->name()} ({$e->employee_number})",
            ],
            'investors' => [
                Investor::query()->when($q !== '', fn (Builder $b) => $b->where('name', 'like', $like))->orderBy('name'),
                'ulid', static fn (Investor $i): string => $i->name,
            ],
            'budget-lines' => [
                BudgetLine::query()->whereHas('project', fn (Builder $b) => $b->where('ulid', $request->string('project')->toString()))
                    ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('code', 'like', $like)->orWhere('description', 'like', $like)))
                    ->orderBy('code'),
                'id', static fn (BudgetLine $l): string => "{$l->code} {$l->description}",
            ],
            default => abort(404),
        };

        if ($key !== '') {
            $query->where($keyColumn, $key);
        }

        $rows = $query->limit(self::LIMIT)->get()->map(static fn ($m): array => ['key' => (string) $m->getAttribute($keyColumn), 'label' => $label($m)])->values();

        return response()->json(['data' => $rows]);
    }
}
