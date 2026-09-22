<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Http\Controllers;

use App\Domains\Integrations\Models\IntegrationSync;
use App\Domains\Integrations\Services\IntegrationException;
use App\Domains\Integrations\Services\IntegrationRepository;
use App\Domains\Integrations\Services\SageZaConnector;
use App\Domains\Integrations\Services\SimplePayConnector;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workforce\Models\Employee;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationController
{
    public function __construct(
        private readonly IntegrationRepository $repo,
        private readonly SageZaConnector $sage,
        private readonly SimplePayConnector $simplepay,
    ) {}

    public function index(): Response
    {
        Gate::authorize('manage-integrations');
        $providers = [];
        foreach (IntegrationRepository::PROVIDERS as $key => $label) {
            $i = $this->repo->get($key);
            $creds = $i->credentials ?? [];
            $providers[] = [
                'key' => $key, 'label' => $label, 'enabled' => $i->enabled, 'settings' => $i->settings ?? [],
                // Secrets are never sent back to the browser, only whether they are set.
                'credentials' => array_map(static fn ($v): bool => $v !== null && $v !== '', $creds),
                'lastSynced' => $i->last_synced_at?->toIso8601String(), 'lastError' => $i->last_error,
            ];
        }

        return Inertia::render('settings/integrations', [
            'providers' => $providers,
            'syncs' => IntegrationSync::query()->latest('updated_at')->limit(30)->get()->map(static fn (IntegrationSync $s): array => [
                'provider' => $s->provider, 'type' => str_replace('_', ' ', $s->entity_type), 'id' => $s->entity_id, 'status' => $s->status,
                'externalId' => $s->external_id, 'error' => $s->error, 'attempts' => $s->attempts, 'at' => $s->updated_at->toIso8601String(),
            ]),
            // Records that still need their ID in the other system, so they can be sent.
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->limit(200)->get(['ulid', 'name', 'accounting_ref'])
                ->map(static fn (Supplier $s): array => ['id' => $s->ulid, 'name' => $s->name, 'ref' => $s->accounting_ref])->values(),
            'employees' => Employee::query()->where('status', 'active')->orderBy('last_name')->limit(500)->get(['ulid', 'first_name', 'last_name', 'employee_number', 'payroll_ref'])
                ->map(static fn (Employee $e): array => ['id' => $e->ulid, 'name' => "{$e->name()} ({$e->employee_number})", 'ref' => $e->payroll_ref])->values(),
        ]);
    }

    public function save(Request $request, string $provider): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        abort_unless(isset(IntegrationRepository::PROVIDERS[$provider]), 404);
        $data = $request->validate([
            'enabled' => ['boolean'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:500'],
            'settings' => ['nullable', 'array'],
        ]);

        $integration = $this->repo->get($provider);
        // Blank secret fields keep the stored value, so admins never have to re-type them.
        $credentials = $integration->credentials ?? [];
        foreach ((array) ($data['credentials'] ?? []) as $k => $v) {
            if ($v !== null && $v !== '') {
                $credentials[(string) $k] = (string) $v;
            }
        }
        $integration->forceFill(['enabled' => (bool) ($data['enabled'] ?? false), 'credentials' => $credentials, 'settings' => $data['settings'] ?? []])->save();
        activity('integrations')->causedBy($request->user())->withProperties(['provider' => $provider])->log('Integration settings changed');

        return back()->with('success', IntegrationRepository::PROVIDERS[$provider].' settings saved.');
    }

    public function test(string $provider): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $integration = $this->repo->get($provider);

        try {
            $names = $provider === 'sage_za' ? $this->sage->test($integration) : $this->simplepay->test($integration);
        } catch (IntegrationException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable) {
            return back()->with('error', 'Could not reach '.IntegrationRepository::PROVIDERS[$provider].'.');
        }

        return back()->with('success', 'Connected. Companies on this account: '.(implode(', ', $names) ?: 'none listed').'.');
    }

    public function sync(Request $request, string $provider): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $integration = $this->repo->get($provider);

        try {
            if ($provider === 'sage_za') {
                $r = $this->sage->pushApprovedInvoices($integration);
                $msg = "{$r['sent']} invoices sent to Sage".($r['failed'] ? ", {$r['failed']} failed (see the log below)" : '').'.';
            } else {
                $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
                $from = Carbon::parse((string) $data['from']);
                $to = Carbon::parse((string) $data['to']);
                $inputs = $this->simplepay->pushPayslipInputs($integration, $from, $to);
                $leave = $this->simplepay->pushLeave($integration, $from, $to);
                $r = ['skipped' => [...$inputs['skipped'], ...$leave['skipped']], 'failed' => [...$inputs['failed'], ...$leave['failed']]];
                $msg = "{$inputs['sent']} payslips updated and {$leave['sent']} leave requests sent to SimplePay".($r['failed'] ? '. Failed: '.implode('; ', array_slice($r['failed'], 0, 5)) : '').'.';
            }
        } catch (IntegrationException $e) {
            return back()->with('error', $e->getMessage());
        } catch (RequestException $e) {
            return back()->with('error', 'The other system returned an error: '.$e->response->status());
        }

        if ($r['skipped'] !== []) {
            $msg .= ' Not sent: '.implode('; ', array_slice($r['skipped'], 0, 5)).'.';
        }

        return back()->with($r['failed'] ? 'error' : 'success', $msg);
    }

    public function supplierRef(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $supplier->update($request->validate(['accounting_ref' => ['nullable', 'string', 'max:40']]));

        return back()->with('success', 'Sage supplier ID saved.');
    }

    public function employeeRef(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $employee->update($request->validate(['payroll_ref' => ['nullable', 'string', 'max:40', Rule::unique('employees', 'payroll_ref')->ignore($employee->id)->where('company_id', $employee->getAttribute('company_id'))]]));

        return back()->with('success', 'SimplePay employee ID saved.');
    }
}
