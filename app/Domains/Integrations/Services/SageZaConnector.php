<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Integrations\Models\Integration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sage Business Cloud Accounting, South African edition (API v2.0.0, formerly Sage One).
 *
 * URLs take the form [base]/[Service]/[Method]?apikey=...&companyid=...; calls use Basic authentication
 * with the Sage user's email and password; Save methods take the entity as JSON. Sage allows 5 000 calls
 * a day per company. Field names follow the v2.0.0 specification and must be confirmed against the
 * client's sandbox before go-live (docs/assumptions.md IN1).
 */
final class SageZaConnector
{
    public const string PROVIDER = 'sage_za';

    public const string DEFAULT_BASE = 'https://resellers.accounting.sageone.co.za/api/2.0.0';

    public function __construct(private readonly IntegrationRepository $repo) {}

    /**
     * @return list<string> names of the companies the credentials can see
     */
    public function test(Integration $integration): array
    {
        $response = $this->http($integration)->get($this->url($integration, 'Company/Get', withCompany: false));
        if (! $response->successful()) {
            throw new IntegrationException('Sage refused the connection ('.$response->status().'). Check the API key, email and password.');
        }
        /** @var array{Results?: list<array{Name?: string}>} $body */
        $body = $response->json() ?? [];

        return array_values(array_map(static fn (array $c): string => (string) ($c['Name'] ?? ''), $body['Results'] ?? []));
    }

    /**
     * Send approved invoices that have not been sent yet.
     *
     * @return array{sent: int, failed: int, skipped: list<string>}
     */
    public function pushApprovedInvoices(Integration $integration): array
    {
        $this->assertReady($integration);
        $sent = 0;
        $failed = 0;
        $skipped = [];

        SupplierInvoice::query()->with(['supplier', 'budgetLine'])->whereIn('status', ['approved', 'scheduled', 'paid'])->orderBy('invoice_date')->each(
            function (SupplierInvoice $invoice) use ($integration, &$sent, &$failed, &$skipped): void {
                if ($this->repo->alreadySent(self::PROVIDER, 'supplier_invoice', $invoice->id)) {
                    return;
                }
                if ($invoice->supplier->accounting_ref === null) {
                    $skipped[] = "{$invoice->supplier->name} has no Sage supplier ID";

                    return;
                }
                try {
                    $id = $this->pushInvoice($integration, $invoice);
                    $this->repo->record(self::PROVIDER, 'supplier_invoice', $invoice->id, true, $id, null);
                    $sent++;
                } catch (Throwable $e) {
                    $this->repo->record(self::PROVIDER, 'supplier_invoice', $invoice->id, false, null, mb_substr($e->getMessage(), 0, 1000));
                    $failed++;
                }
            },
        );

        $integration->forceFill(['last_synced_at' => now(), 'last_error' => $failed ? "{$failed} invoices failed to send." : null])->save();

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => array_values(array_unique($skipped))];
    }

    /**
     * @return string Sage's ID for the new supplier invoice
     *
     * @throws RequestException
     */
    public function pushInvoice(Integration $integration, SupplierInvoice $invoice): string
    {
        $settings = $integration->settings ?? [];
        /** @var array<string, string> $accounts */
        $accounts = (array) ($settings['accounts'] ?? []);
        $account = $accounts[$invoice->budgetLine?->code ?? ''] ?? ($settings['default_account_id'] ?? null);
        if ($account === null || $account === '') {
            throw new IntegrationException('No Sage account is mapped for cost code '.($invoice->budgetLine->code ?? '(none)').' and there is no default account.');
        }
        $taxType = (float) $invoice->vat > 0 ? ($settings['tax_type_vat'] ?? null) : ($settings['tax_type_none'] ?? null);

        $body = [
            'SupplierId' => (int) $invoice->supplier->accounting_ref,
            'Date' => $invoice->invoice_date->toDateString(),
            'DueDate' => $invoice->due_date->toDateString(),
            'Reference' => $invoice->invoice_number,
            'Inclusive' => false,
            'Lines' => [[
                'LineType' => 1, // 1 = general ledger account line
                'SelectionId' => (int) $account,
                'TaxTypeId' => $taxType === null ? null : (int) $taxType,
                'Description' => mb_substr("Invoice {$invoice->invoice_number} ".($invoice->purchaseOrder?->reference() ?? ''), 0, 100),
                'Quantity' => 1,
                'UnitPriceExclusive' => (float) $invoice->subtotal,
            ]],
        ];

        $response = $this->http($integration)->post($this->url($integration, 'SupplierInvoice/Save'), $body)->throw();
        /** @var array{ID?: int|string} $saved */
        $saved = $response->json() ?? [];

        return (string) ($saved['ID'] ?? '');
    }

    private function assertReady(Integration $integration): void
    {
        $c = $integration->credentials ?? [];
        if (! $integration->enabled || empty($c['api_key']) || empty($c['username']) || empty($c['password']) || empty(($integration->settings ?? [])['company_id'])) {
            throw new IntegrationException('Sage is not set up: switch it on and enter the API key, login and Sage company ID.');
        }
    }

    private function http(Integration $integration): PendingRequest
    {
        $c = $integration->credentials ?? [];

        return Http::withBasicAuth((string) ($c['username'] ?? ''), (string) ($c['password'] ?? ''))->acceptJson()->asJson()->timeout(30)->retry(2, 1000, throw: false);
    }

    private function url(Integration $integration, string $path, bool $withCompany = true): string
    {
        $settings = $integration->settings ?? [];
        $base = rtrim((string) ($settings['base_url'] ?? self::DEFAULT_BASE), '/');
        $query = ['apikey' => (string) (($integration->credentials ?? [])['api_key'] ?? '')];
        if ($withCompany) {
            $query['companyid'] = (string) ($settings['company_id'] ?? '');
        }

        return "{$base}/{$path}?".http_build_query($query);
    }
}
