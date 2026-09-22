<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Http\Controllers;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Documents\Services\DocumentService;
use App\Domains\MasterData\Services\MasterDataService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Procurement\Exceptions\ProcurementException;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\Requisition;
use App\Domains\Procurement\Models\RequisitionLine;
use App\Domains\Procurement\Models\RequisitionQuote;
use App\Domains\Procurement\Services\ProcurementService;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Exceptions\SupplierNotCompliantException;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\ApprovalStep;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RequisitionController
{
    public function __construct(
        private readonly ProcurementService $procurement,
        private readonly ComplianceService $compliance,
        private readonly DocumentService $documents,
        private readonly CurrentCompany $context,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        return Inertia::render('procurement/requisitions', [
            'requisitions' => Requisition::query()->with(['project:id,ulid,name', 'requester:id,name'])->withCount('quotes')
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(static fn (Requisition $r): array => [
                    'id' => $r->ulid, 'reference' => $r->reference(), 'title' => $r->title, 'project' => $r->project->name,
                    'status' => $r->status, 'total' => (float) $r->estimated_total, 'quotes' => (int) $r->getAttribute('quotes_count'),
                    'neededBy' => $r->needed_by?->toDateString(), 'by' => $r->requester->name,
                ]),
            'filter' => $status,
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(['ulid', 'name'])->map(static fn (Project $p): array => ['key' => $p->ulid, 'label' => $p->name])->values(),
            'units' => app(MasterDataService::class)->unitOptions(),
            'canRaise' => $request->user()?->can('raise-requisitions') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('raise-requisitions');
        $data = $request->validate([
            'project' => ['required', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())],
            'title' => ['required', 'string', 'max:200'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:16'],
            'lines.*.estimated_unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var list<array{description: string, quantity: float|string, unit: string, estimated_unit_price: float|string}> $lines */
        $lines = array_values($data['lines']);

        $requisition = $this->procurement->createRequisition(
            Project::query()->where('ulid', $data['project'])->firstOrFail(),
            (string) $data['title'], $data['needed_by'] ?? null, $data['notes'] ?? null, $lines, $user,
        );

        if ($request->boolean('submit')) {
            try {
                $this->procurement->submitRequisition($requisition, $user);
            } catch (ApprovalException|ProcurementException $e) {
                return redirect()->route('requisitions.show', $requisition)->with('error', $e->getMessage());
            }
        }

        return redirect()->route('requisitions.show', $requisition)->with('success', "{$requisition->reference()} ".($request->boolean('submit') ? 'submitted for approval.' : 'saved as a draft.'));
    }

    public function show(Request $request, Requisition $requisition): Response
    {
        $requisition->load(['project:id,ulid,name,code', 'requester:id,name', 'lines', 'quotes.supplier']);
        $approval = $requisition->approvals()->with('steps.decider:id,name')->first();
        $lowest = $requisition->quotes->min('amount');

        return Inertia::render('procurement/requisition', [
            'requisition' => [
                'id' => $requisition->ulid, 'reference' => $requisition->reference(), 'title' => $requisition->title,
                'project' => ['id' => $requisition->project->ulid, 'name' => $requisition->project->name],
                'status' => $requisition->status, 'total' => (float) $requisition->estimated_total, 'notes' => $requisition->notes,
                'neededBy' => $requisition->needed_by?->toDateString(), 'by' => $requisition->requester->name,
                'awardedQuoteId' => $requisition->awarded_quote_id, 'awardReason' => $requisition->award_reason, 'singleSourceReason' => $requisition->single_source_reason,
            ],
            'lines' => $requisition->lines->map(static fn (RequisitionLine $l): array => [
                'description' => $l->description, 'quantity' => (float) $l->quantity, 'unit' => $l->unit, 'price' => (float) $l->estimated_unit_price,
            ]),
            'quotes' => $requisition->quotes->map(fn (RequisitionQuote $q): array => [
                'id' => $q->id, 'supplier' => $q->supplier->name, 'amount' => (float) $q->amount, 'reference' => $q->reference,
                'leadTime' => $q->lead_time_days, 'validUntil' => $q->valid_until?->toDateString(), 'notes' => $q->notes,
                'lowest' => (float) $q->amount === (float) $lowest,
                'blockers' => $this->compliance->blockers($q->supplier, (float) $q->amount * 1.15),
            ]),
            'approval' => $approval ? $this->approvalTrail($approval) : null,
            'purchaseOrder' => $requisition->status === 'awarded'
                ? PurchaseOrder::query()->where('requisition_id', $requisition->id)->value('ulid') : null,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(['ulid', 'name'])->map(static fn (Supplier $s): array => ['key' => $s->ulid, 'label' => $s->name])->values(),
            'threshold' => (float) config('delegation_of_authority.three_quote_threshold'),
            'invitations' => RfqController::invitationsFor($requisition),
            'can' => [
                'submit' => $requisition->status === 'draft' && $request->user()?->id === $requisition->requested_by,
                'procure' => $request->user()?->can('manage-procurement') ?? false,
            ],
        ]);
    }

    public function submit(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless($request->user()?->id === $requisition->requested_by, 403);
        /** @var User $user */
        $user = $request->user();

        try {
            $this->procurement->submitRequisition($requisition, $user);
        } catch (ApprovalException|ProcurementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Submitted for approval.');
    }

    public function storeQuote(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('manage-procurement');
        $data = $request->validate([
            'supplier' => ['required', 'string', Rule::exists('suppliers', 'ulid')->where('company_id', $this->context->id())],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:60'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,xlsx,docx', 'max:10240'],
        ]);

        $supplier = Supplier::query()->where('ulid', $data['supplier'])->firstOrFail();
        if (RequisitionQuote::query()->where('requisition_id', $requisition->id)->where('supplier_id', $supplier->id)->exists()) {
            return back()->with('error', "A quote from {$supplier->name} is already recorded.");
        }

        /** @var User $user */
        $user = $request->user();
        $documentId = null;
        if ($request->hasFile('file')) {
            $documentId = $this->documents->upload($request->file('file'), [
                'project_id' => $requisition->project_id, 'folder' => 'Procurement/Quotes',
                'title' => "Quote {$supplier->name} for {$requisition->reference()}", 'category' => DocumentCategory::Financial,
                'restricted_to_roles' => [Role::Procurement->value, Role::Finance->value, Role::DevelopmentManager->value],
            ], $user)->id;
        }

        RequisitionQuote::query()->create([
            'requisition_id' => $requisition->id, 'supplier_id' => $supplier->id, 'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null, 'lead_time_days' => $data['lead_time_days'] ?? null,
            'valid_until' => $data['valid_until'] ?? null, 'notes' => $data['notes'] ?? null, 'document_id' => $documentId,
        ]);

        return back()->with('success', "Quote from {$supplier->name} recorded.");
    }

    public function award(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('manage-procurement');
        $data = $request->validate([
            'quote' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'single_source_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $quote = RequisitionQuote::query()->with('supplier')->findOrFail((int) $data['quote']);
        /** @var User $user */
        $user = $request->user();

        try {
            $order = $this->procurement->award($requisition, $quote, $data['reason'] ?? null, $data['single_source_reason'] ?? null, $user);
        } catch (ProcurementException|SupplierNotCompliantException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase-orders.show', $order)->with('success', "{$order->reference()} drafted. Check the line prices, then submit it for approval.");
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalTrail(ApprovalRequest $approval): array
    {
        return [
            'status' => $approval->status,
            'steps' => $approval->steps->map(static fn (ApprovalStep $s): array => [
                'sequence' => $s->sequence, 'role' => Role::tryFrom($s->role)?->label() ?? $s->role, 'decision' => $s->decision,
                'by' => $s->decider?->name, 'at' => $s->decided_at?->toIso8601String(), 'comment' => $s->comment,
                'current' => $approval->status === 'pending' && $approval->current_step === $s->sequence,
            ])->values(),
        ];
    }
}
