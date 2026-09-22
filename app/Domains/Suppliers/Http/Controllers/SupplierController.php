<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Http\Controllers;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Services\DocumentService;
use App\Domains\Platform\Enums\Province;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Enums\ComplianceState;
use App\Domains\Suppliers\Enums\SupplierType;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Models\SupplierDocument;
use App\Domains\Suppliers\Models\SupplierRating;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController
{
    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly DocumentService $documents,
        private readonly CurrentCompany $context,
    ) {}

    public function index(Request $request): Response
    {
        $type = SupplierType::tryFrom($request->string('type')->toString());
        $term = trim($request->string('q')->toString());

        $suppliers = Supplier::query()
            ->withAvg('ratings as avg_quality', 'quality')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('trading_name', 'like', $like)->orWhere('registration_number', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Supplier $s): array {
                $rows = $this->compliance->evaluate($s);

                return [
                    'id' => $s->ulid,
                    'name' => $s->name,
                    'type' => $s->type->label(),
                    'cidb' => $s->cidb_grade ? "{$s->cidb_grade}{$s->cidb_class}" : null,
                    'bbbee' => $s->bbbee_level,
                    'status' => $s->status,
                    'compliant' => $this->compliance->isCompliant($s),
                    'expiring' => count(array_filter($rows, static fn (array $r): bool => $r['state'] === ComplianceState::Expiring)),
                    'problems' => count(array_filter($rows, static fn (array $r): bool => in_array($r['state'], [ComplianceState::Missing, ComplianceState::Expired], true))),
                ];
            });

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'types' => array_map(static fn (SupplierType $t): array => ['key' => $t->value, 'label' => $t->label()], SupplierType::cases()),
            'filters' => ['type' => $type?->value, 'q' => $term],
            'canManage' => $request->user()?->can('manage-suppliers') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-suppliers');
        $supplier = Supplier::query()->create($this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier added. Upload their compliance documents next.');
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $supplier->load(['ratings.project:id,name', 'ratings.rater:id,name']);

        return Inertia::render('suppliers/show', [
            'supplier' => [
                ...$supplier->only(['name', 'trading_name', 'registration_number', 'vat_number', 'cidb_crs_number', 'cidb_grade', 'cidb_class', 'bbbee_level', 'contact_name', 'email', 'phone', 'status', 'notes']),
                'id' => $supplier->ulid,
                'type' => $supplier->type->value,
                'typeLabel' => $supplier->type->label(),
                'province' => $supplier->province?->value,
                'cidbLimit' => $this->compliance->cidbLimit($supplier->cidb_grade),
            ],
            'compliance' => $this->complianceRows($supplier),
            'blockers' => $this->compliance->blockers($supplier),
            'ratings' => $supplier->ratings->map(static fn (SupplierRating $r): array => [
                'id' => $r->id, 'project' => $r->project?->name, 'quality' => $r->quality, 'timeliness' => $r->timeliness,
                'safety' => $r->safety, 'average' => $r->average(), 'comment' => $r->comment, 'by' => $r->rater?->name,
                'at' => $r->created_at->toIso8601String(),
            ]),
            'documentTypes' => collect((array) config('supplier_compliance.documents'))->map(static fn (array $d, string $k): array => ['key' => $k, 'label' => (string) $d['label']])->values(),
            'types' => array_map(static fn (SupplierType $t): array => ['key' => $t->value, 'label' => $t->label()], SupplierType::cases()),
            'provinces' => array_map(static fn (Province $p): array => ['key' => $p->value, 'label' => $p->label()], Province::cases()),
            'projects' => Project::query()->orderBy('name')->get(['ulid', 'name'])->map(static fn (Project $p): array => ['key' => $p->ulid, 'label' => $p->name])->values(),
            'can' => [
                'manage' => $request->user()?->can('manage-suppliers') ?? false,
                'rate' => $request->user()?->can('manage-projects') ?? false,
            ],
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('manage-suppliers');
        $supplier->update($this->validated($request));

        return back()->with('success', 'Supplier details saved.');
    }

    public function storeDocument(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('manage-suppliers');

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys((array) config('supplier_compliance.documents')))],
            'reference' => ['nullable', 'string', 'max:80'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after:issued_on'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $documentId = null;

        if ($request->hasFile('file')) {
            try {
                $label = (string) config("supplier_compliance.documents.{$data['type']}.label");
                $document = $this->documents->upload($request->file('file'), [
                    'folder' => 'Suppliers/'.$supplier->name,
                    'title' => "{$label}: {$supplier->name}",
                    'category' => DocumentCategory::Compliance,
                    'restricted_to_roles' => [Role::Procurement->value, Role::Finance->value],
                ], $user);
                $documentId = $document->id;
            } catch (QuotaExceededException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        SupplierDocument::query()->create([
            'supplier_id' => $supplier->id,
            'type' => $data['type'],
            'reference' => $data['reference'] ?? null,
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'document_id' => $documentId,
        ]);

        return back()->with('success', 'Compliance document recorded.');
    }

    public function verifyDocument(Request $request, Supplier $supplier, SupplierDocument $document): RedirectResponse
    {
        Gate::authorize('manage-suppliers');
        abort_unless($document->supplier_id === $supplier->id, 404);

        /** @var User $user */
        $user = $request->user();
        $document->forceFill(['verified_by' => $user->id, 'verified_at' => now()])->save();

        return back()->with('success', 'Document verified.');
    }

    public function status(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('manage-suppliers');
        $data = $request->validate(['status' => ['required', 'in:active,suspended'], 'reason' => ['nullable', 'string', 'max:500']]);
        $supplier->update(['status' => $data['status']]);

        activity('suppliers')->causedBy($request->user())->performedOn($supplier)
            ->withProperties(['reason' => $data['reason'] ?? null])->log($data['status'] === 'suspended' ? 'Supplier suspended' : 'Supplier reactivated');

        return back()->with('success', $data['status'] === 'suspended' ? "{$supplier->name} suspended." : "{$supplier->name} reactivated.");
    }

    public function rate(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('manage-projects');

        $data = $request->validate([
            'project' => ['nullable', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())],
            'quality' => ['required', 'integer', 'between:1,5'],
            'timeliness' => ['required', 'integer', 'between:1,5'],
            'safety' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        SupplierRating::query()->create([
            'supplier_id' => $supplier->id,
            'project_id' => isset($data['project']) ? Project::query()->where('ulid', $data['project'])->value('id') : null,
            'quality' => $data['quality'], 'timeliness' => $data['timeliness'], 'safety' => $data['safety'],
            'comment' => $data['comment'] ?? null, 'rated_by' => $user->id,
        ]);

        return back()->with('success', 'Rating saved.');
    }

    /**
     * Compliance rows plus the latest file version for downloading the copy on record.
     *
     * @return list<array<string, mixed>>
     */
    private function complianceRows(Supplier $supplier): array
    {
        $rows = $this->compliance->evaluate($supplier);
        $ulids = array_values(array_filter(array_column($rows, 'documentId')));
        $versions = Document::query()->whereIn('ulid', $ulids)->with('latestVersion')->get()
            ->mapWithKeys(static fn (Document $d): array => [$d->ulid => $d->latestVersion?->id]);

        return array_map(static fn (array $r): array => [
            ...$r,
            'state' => $r['state']->value,
            'versionId' => $r['documentId'] !== null ? ($versions[$r['documentId']] ?? null) : null,
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'type' => ['required', Rule::enum(SupplierType::class)],
            'registration_number' => ['nullable', 'string', 'max:32', 'regex:/^(\d{4}\/\d{6}\/\d{2}|[A-Z0-9\/\-]+)$/'],
            'vat_number' => ['nullable', 'regex:/^4\d{9}$/'],
            'cidb_crs_number' => ['nullable', 'string', 'max:20'],
            'cidb_grade' => ['nullable', 'integer', 'between:1,9'],
            'cidb_class' => ['nullable', 'string', 'max:8'],
            'bbbee_level' => ['nullable', 'in:1,2,3,4,5,6,7,8,non_compliant'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'province' => ['nullable', Rule::enum(Province::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['vat_number.regex' => 'A South African VAT number is 10 digits starting with 4.']);
    }
}
