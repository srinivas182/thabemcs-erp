<?php

declare(strict_types=1);

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Documents\Services\DocumentService;
use App\Domains\Finance\Exceptions\FinanceException;
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly DocumentService $documents,
        private readonly CurrentCompany $context,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('manage-finance');
        $status = $request->string('status')->toString();

        return Inertia::render('finance/invoices', [
            'invoices' => SupplierInvoice::query()->with(['supplier:id,name', 'project:id,name', 'purchaseOrder:id,number', 'approver:id,name'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'exception' then 0 when 'captured' then 1 when 'matched' then 2 else 3 end")
                ->orderBy('due_date')->paginate(30)->withQueryString()
                ->through(static fn (SupplierInvoice $i): array => [
                    'id' => $i->ulid, 'number' => $i->invoice_number, 'supplier' => $i->supplier->name, 'project' => $i->project->name,
                    'po' => $i->purchaseOrder?->reference(), 'date' => $i->invoice_date->toDateString(), 'due' => $i->due_date->toDateString(),
                    'subtotal' => (float) $i->subtotal, 'vat' => (float) $i->vat, 'total' => (float) $i->total, 'status' => $i->status,
                    'issues' => $i->match_issues ?? [], 'override' => $i->override_reason, 'approver' => $i->approver?->name,
                    'mine' => $i->captured_by === $request->user()?->id,
                ]),
            'filter' => $status,
            'suppliers' => Supplier::query()->orderBy('name')->get(['ulid', 'name'])->map(static fn (Supplier $s): array => ['key' => $s->ulid, 'label' => $s->name])->values(),
            'orders' => PurchaseOrder::query()->with('supplier:id,ulid,name')->whereIn('status', ['issued', 'partially_received', 'received'])->latest('id')->limit(200)->get()
                ->map(static fn (PurchaseOrder $o): array => ['key' => $o->ulid, 'label' => "{$o->reference()} {$o->supplier->name}", 'supplier' => $o->supplier->ulid])->values(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'ulid', 'name'])->map(static fn (Project $p): array => ['key' => $p->ulid, 'label' => $p->name])->values(),
            'budgetLines' => BudgetLine::query()->with('project:id,ulid')->orderBy('code')->get()
                ->map(static fn (BudgetLine $l): array => ['key' => (string) $l->id, 'label' => "{$l->code} {$l->description}", 'project' => $l->project->ulid])->values(),
            'canOverride' => $request->user()?->can('override-invoice-match') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-finance');
        $companyId = $this->context->id();
        $supplier = Supplier::query()->where('ulid', $request->string('supplier')->toString())->first();

        $data = $request->validate([
            'supplier' => ['required', 'string', Rule::exists('suppliers', 'ulid')->where('company_id', $companyId)],
            'purchase_order' => ['nullable', 'string', Rule::exists('purchase_orders', 'ulid')->where('company_id', $companyId)],
            'project' => ['required_without:purchase_order', 'nullable', 'string', Rule::exists('projects', 'ulid')->where('company_id', $companyId)],
            'budget_line_id' => ['required_without:purchase_order', 'nullable', 'integer', Rule::exists('budget_lines', 'id')->where('company_id', $companyId)],
            'invoice_number' => ['required', 'string', 'max:60', Rule::unique('supplier_invoices')->where('supplier_id', $supplier?->id)],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'subtotal' => ['required', 'numeric', 'gt:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'invoice_number.unique' => 'This invoice number has already been captured for this supplier.',
            'budget_line_id.required_without' => 'Choose the cost code for an invoice without a purchase order.',
        ]);

        $order = isset($data['purchase_order']) ? PurchaseOrder::query()->where('ulid', $data['purchase_order'])->first() : null;
        $projectId = $order?->project_id ?? Project::query()->where('ulid', $data['project'])->value('id');

        /** @var User $user */
        $user = $request->user();
        $documentId = $request->hasFile('file') ? $this->documents->upload($request->file('file'), [
            'project_id' => $projectId, 'folder' => 'Finance/Invoices', 'title' => "Invoice {$data['invoice_number']} {$supplier?->name}",
            'category' => DocumentCategory::Financial, 'restricted_to_roles' => [Role::Finance->value, Role::Procurement->value],
        ], $user)->id : null;

        $invoice = SupplierInvoice::query()->create([
            'project_id' => $projectId, 'supplier_id' => $supplier?->id, 'purchase_order_id' => $order?->id,
            'budget_line_id' => $order?->budget_line_id ?? (isset($data['budget_line_id']) ? (int) $data['budget_line_id'] : null),
            'invoice_number' => $data['invoice_number'], 'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'],
            'subtotal' => $data['subtotal'], 'vat' => $data['vat'], 'total' => $data['total'], 'document_id' => $documentId, 'captured_by' => $user->id,
        ]);

        $issues = $this->invoices->match($invoice);

        return back()->with($issues ? 'error' : 'success', $issues
            ? "Invoice {$invoice->invoice_number} captured but failed the match: ".implode(' ', $issues)
            : "Invoice {$invoice->invoice_number} captured and matched.");
    }

    public function approve(Request $request, SupplierInvoice $invoice): RedirectResponse
    {
        Gate::authorize('manage-finance');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->invoices->approve($invoice, $user, $request->filled('override_reason') ? $request->string('override_reason')->toString() : null);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} approved for payment.");
    }

    public function reject(Request $request, SupplierInvoice $invoice): RedirectResponse
    {
        Gate::authorize('manage-finance');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        /** @var User $user */
        $user = $request->user();

        try {
            $this->invoices->reject($invoice, (string) $data['reason'], $user);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice rejected.');
    }

    public function rematch(SupplierInvoice $invoice): RedirectResponse
    {
        Gate::authorize('manage-finance');
        abort_unless(in_array($invoice->status, ['captured', 'matched', 'exception'], true), 422);

        $issues = $this->invoices->match($invoice);

        return back()->with($issues ? 'error' : 'success', $issues ? 'Still not matching: '.implode(' ', $issues) : 'The invoice now matches.');
    }
}
