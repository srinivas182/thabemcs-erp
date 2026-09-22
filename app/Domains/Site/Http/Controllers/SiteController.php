<?php

declare(strict_types=1);

namespace App\Domains\Site\Http\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Site\Models\Delivery;
use App\Domains\Site\Models\Inspection;
use App\Domains\Site\Models\SiteAttendance;
use App\Domains\Site\Models\SiteDiary;
use App\Domains\Site\Models\SiteInstruction;
use App\Domains\Site\Models\SitePhoto;
use App\Domains\Site\Models\Snag;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workforce\Models\CrewAttendance;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Management view of what happens on site: diary, attendance, photos, deliveries,
 * site instructions, quality inspections and snags.
 */
final class SiteController
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function show(Request $request, Project $project): Response
    {
        $today = Carbon::today('Africa/Johannesburg');

        return Inertia::render('projects/site', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code, 'hasLocation' => $project->getAttribute('latitude') !== null],
            'diaries' => SiteDiary::query()->with('author:id,name')->where('project_id', $project->id)->orderByDesc('diary_date')->limit(30)->get()
                ->map(static fn (SiteDiary $d): array => [
                    'id' => $d->id, 'date' => $d->diary_date->toDateString(), 'weather' => $d->weather->label()
                        .($d->temperature_max !== null ? ', '.(float) $d->temperature_max.'°C' : '').($d->rain_mm !== null && (float) $d->rain_mm > 0 ? ', '.(float) $d->rain_mm.' mm rain' : ''),
                    'workers' => $d->workers_on_site,
                    'work' => $d->work_completed, 'delays' => $d->delays, 'by' => $d->author?->name,
                ]),
            'attendance' => SiteAttendance::query()->with('user:id,name')->where('project_id', $project->id)
                ->where('captured_at', '>=', $today->copy()->subDays(6)->startOfDay()->utc())->orderByDesc('captured_at')->limit(200)->get()
                ->map(static fn (SiteAttendance $a): array => [
                    'id' => $a->id, 'name' => $a->user->name, 'direction' => $a->direction, 'at' => $a->captured_at->toIso8601String(),
                    'within' => $a->within_geofence, 'distance' => $a->distance_m, 'hasSelfie' => $a->selfie_path !== null,
                ]),
            'photos' => SitePhoto::query()->where('project_id', $project->id)->orderByDesc('captured_at')->limit(48)->get()
                ->map(static fn (SitePhoto $p): array => ['id' => $p->id, 'caption' => $p->caption, 'at' => $p->captured_at->toIso8601String(), 'geotagged' => $p->latitude !== null]),
            'deliveries' => Delivery::query()->with('receiver:id,name')->where('project_id', $project->id)->orderByDesc('received_at')->limit(50)->get()
                ->map(static fn (Delivery $d): array => [
                    'id' => $d->ulid, 'supplier' => $d->supplier_name, 'note' => $d->delivery_note_number, 'items' => $d->items,
                    'condition' => $d->condition, 'notes' => $d->notes, 'at' => $d->received_at->toIso8601String(), 'by' => $d->receiver?->name,
                ]),
            'instructions' => SiteInstruction::query()->with('issuer:id,name')->where('project_id', $project->id)->orderByDesc('number')->get()
                ->map(static fn (SiteInstruction $i): array => [
                    'id' => $i->ulid, 'number' => $i->number, 'subject' => $i->subject, 'instruction' => $i->instruction,
                    'cost' => $i->cost_implication, 'time' => $i->time_implication, 'status' => $i->status,
                    'at' => $i->issued_at->toIso8601String(), 'by' => $i->issuer?->name,
                    'supplier' => $i->supplier_id ? Supplier::query()->whereKey($i->supplier_id)->value('name') : null,
                ]),
            'inspections' => Inspection::query()->where('project_id', $project->id)->where('kind', 'quality')->orderByDesc('inspected_on')->limit(30)->get()
                ->map(static fn (Inspection $i): array => ['id' => $i->ulid, 'title' => $i->title, 'location' => $i->location, 'result' => $i->result, 'findings' => $i->findings, 'on' => $i->inspected_on->toDateString()]),
            'snags' => Snag::query()->where('project_id', $project->id)->orderByRaw("case status when 'open' then 0 when 'fixed' then 1 else 2 end")->orderBy('due_on')->get()
                ->map(static fn (Snag $s): array => [
                    'id' => $s->ulid, 'location' => $s->location, 'description' => $s->description, 'status' => $s->status,
                    'dueOn' => $s->due_on?->toDateString(), 'supplier' => $s->supplier_id ? Supplier::query()->whereKey($s->supplier_id)->value('name') : null,
                ]),
            'crew' => CrewAttendance::query()->with('employee')->where('project_id', $project->id)
                ->whereDate('worked_on', '>=', $today->copy()->subDays(6)->toDateString())->orderByDesc('worked_on')->get()
                ->groupBy(static fn ($c) => $c->worked_on->toDateString())
                ->map(static fn ($rows, $date): array => ['date' => $date, 'present' => $rows->where('status', 'present')->count(), 'absent' => $rows->where('status', '!=', 'present')->count()])
                ->values(),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(['ulid', 'name'])->map(static fn (Supplier $s): array => ['key' => $s->ulid, 'label' => $s->name])->values(),
            'canManage' => $request->user()?->can('manage-site') ?? false,
        ]);
    }

    public function storeInstruction(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-site');
        $data = $request->validate([
            'supplier' => ['nullable', 'string', Rule::exists('suppliers', 'ulid')->where('company_id', $this->context->id())],
            'subject' => ['required', 'string', 'max:200'],
            'instruction' => ['required', 'string', 'max:5000'],
            'cost_implication' => ['boolean'],
            'time_implication' => ['boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $instruction = DB::transaction(fn () => SiteInstruction::query()->create([
            'project_id' => $project->id,
            'number' => (int) SiteInstruction::query()->where('project_id', $project->id)->lockForUpdate()->max('number') + 1,
            'supplier_id' => isset($data['supplier']) ? Supplier::query()->where('ulid', $data['supplier'])->value('id') : null,
            'subject' => $data['subject'],
            'instruction' => $data['instruction'],
            'cost_implication' => (bool) ($data['cost_implication'] ?? false),
            'time_implication' => (bool) ($data['time_implication'] ?? false),
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]));

        return back()->with('success', "Site instruction SI-{$instruction->number} issued.");
    }

    public function updateInstruction(Request $request, SiteInstruction $instruction): RedirectResponse
    {
        Gate::authorize('manage-site');
        $instruction->update($request->validate(['status' => ['required', 'in:issued,acknowledged,closed']]));

        return back();
    }

    public function storeInspection(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize($request->input('kind') === 'safety' ? 'manage-safety' : 'manage-site');
        $data = $request->validate([
            'kind' => ['required', 'in:quality,safety'],
            'title' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'result' => ['required', 'in:pass,fail,partial'],
            'findings' => ['nullable', 'required_unless:result,pass', 'string', 'max:5000'],
            'inspected_on' => ['required', 'date', 'before_or_equal:today'],
        ], ['findings.required_unless' => 'Record what failed so it can be fixed.']);

        /** @var User $user */
        $user = $request->user();
        Inspection::query()->create([...$data, 'project_id' => $project->id, 'inspected_by' => $user->id]);

        return back()->with('success', 'Inspection recorded.');
    }

    public function storeSnag(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-site');
        $data = $request->validate([
            'location' => ['nullable', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', Rule::exists('suppliers', 'ulid')->where('company_id', $this->context->id())],
            'due_on' => ['nullable', 'date'],
        ]);

        Snag::query()->create([
            'project_id' => $project->id, 'location' => $data['location'] ?? null, 'description' => $data['description'],
            'supplier_id' => isset($data['supplier']) ? Supplier::query()->where('ulid', $data['supplier'])->value('id') : null,
            'due_on' => $data['due_on'] ?? null,
        ]);

        return back()->with('success', 'Snag added.');
    }

    /**
     * Snags go open, then fixed (by the contractor), then verified (by our team).
     */
    public function updateSnag(Request $request, Snag $snag): RedirectResponse
    {
        Gate::authorize('manage-site');
        $data = $request->validate(['status' => ['required', 'in:open,fixed,verified']]);

        /** @var User $user */
        $user = $request->user();
        $snag->forceFill([
            'status' => $data['status'],
            'fixed_at' => $data['status'] === 'open' ? null : ($snag->fixed_at ?? now()),
            'verified_by' => $data['status'] === 'verified' ? $user->id : null,
            'verified_at' => $data['status'] === 'verified' ? now() : null,
        ])->save();

        return back();
    }

    public function photo(SitePhoto $photo): StreamedResponse
    {
        return Storage::disk($photo->disk)->response($photo->path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
