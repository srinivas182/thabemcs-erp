<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 19: rentals. Tenants, leases (residential and commercial), monthly billing with escalations
 * and recoveries, receipts and arrears, deposits held in an interest-bearing account, inspections and
 * maintenance requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Units are the same stock as sales; a unit can be for sale, to let, or both.
        Schema::table('sale_units', function (Blueprint $table) {
            $table->string('tenure', 8)->default('sale')->after('status')->comment('sale, rental or both');
            $table->decimal('market_rent', 12, 2)->nullable()->after('tenure');
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('entity_type', 12)->default('individual')->comment('individual, company, trust');
            $table->text('id_number')->nullable()->comment('Encrypted: ID or registration number');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('employer')->nullable();
            $table->string('status', 10)->default('applicant')->comment('applicant, approved, current, former, declined');
            $table->boolean('fica_verified')->default(false);
            $table->boolean('credit_checked')->default(false);
            $table->date('screened_on')->nullable();
            $table->string('accounting_ref', 40)->nullable()->comment('Customer ID in the accounting system');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('sale_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('type', 12)->default('residential')->comment('residential or commercial');
            $table->date('signed_on')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable()->comment('Null for month-to-month');
            $table->boolean('month_to_month')->default(false);
            $table->decimal('rent_amount', 12, 2);
            $table->boolean('vat_applies')->default(false)->comment('Commercial leases carry VAT; residential letting is exempt');
            $table->decimal('escalation_percent', 5, 2)->default(0);
            $table->unsignedTinyInteger('payment_day')->default(1);
            $table->unsignedSmallInteger('notice_days')->nullable()->comment('Notice to end the lease');
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->string('deposit_account')->nullable()->comment('Interest-bearing account holding the deposit');
            $table->date('deposit_received_on')->nullable();
            $table->decimal('deposit_interest', 12, 2)->default(0);
            $table->date('deposit_interest_to')->nullable();
            $table->decimal('deposit_deductions', 12, 2)->default(0);
            $table->date('deposit_refunded_on')->nullable();
            $table->char('token_hash', 64)->nullable()->unique()->comment('SHA-256 of the tenant link token');
            $table->string('status', 10)->default('draft')->comment('draft, active, ended, cancelled');
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['sale_unit_id', 'status']);
        });

        Schema::create('lease_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16)->comment('rent, utilities, parking, levy, other');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->boolean('vat_applies')->default(false);
            $table->boolean('escalates')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('lease_invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_on');
            $table->json('lines');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('status', 10)->default('issued')->comment('issued, part_paid, paid, credited');
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->unique(['lease_id', 'period_start']);
            $table->index(['company_id', 'status', 'due_on']);
        });

        Schema::create('lease_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('received_on');
            $table->string('method', 20)->default('eft');
            $table->string('reference', 60)->nullable();
            $table->foreignId('captured_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['lease_id', 'received_on']);
        });

        Schema::create('lease_inspections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->comment('incoming or outgoing');
            $table->date('inspected_on');
            $table->boolean('tenant_present')->default(true);
            $table->json('items')->comment('Areas inspected with their condition');
            $table->text('notes')->nullable();
            $table->foreignId('conducted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_unit_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->text('description');
            $table->string('priority', 8)->default('normal')->comment('urgent, normal, low');
            $table->string('status', 12)->default('new')->comment('new, assigned, in_progress, completed, cancelled');
            $table->boolean('reported_by_tenant')->default(false);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost', 12, 2)->nullable();
            $table->boolean('recover_from_tenant')->default(false);
            $table->date('reported_on');
            $table->date('completed_on')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        foreach (['maintenance_requests', 'lease_inspections', 'lease_receipts', 'lease_invoices', 'lease_charges', 'leases', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('sale_units', fn (Blueprint $t) => $t->dropColumn(['tenure', 'market_rent']));
    }
};
