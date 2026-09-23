<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 18: sales. Stock schedule, buyers, reservations, sale agreements with suspensive conditions,
 * the transfer pipeline through to registration in the Deeds Office, and agent commission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_units', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40)->comment('Erf number or unit number');
            $table->string('type', 20)->comment('erf, house, sectional_unit, commercial');
            $table->string('description')->nullable();
            $table->decimal('size_m2', 10, 2)->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->decimal('list_price', 15, 2)->default(0)->comment('Asking price, incl. VAT where the seller is a VAT vendor');
            $table->boolean('vat_applies')->default(true);
            $table->string('nhbrc_enrolment', 40)->nullable();
            $table->string('status', 12)->default('available')->comment('available, reserved, sold, transferred, withdrawn');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'reference']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('unit_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->date('effective_from');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['sale_unit_id', 'effective_from']);
        });

        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('entity_type', 12)->default('individual')->comment('individual, company, trust');
            $table->text('id_number')->nullable()->comment('Encrypted: ID or registration number');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 12)->default('enquiry')->comment('enquiry, qualified, reserved, purchaser, lost');
            $table->string('source', 30)->nullable()->comment('Walk-in, show house, website, agent, referral');
            $table->foreignId('agent_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete()->comment('Person in the company looking after this buyer');
            $table->boolean('fica_verified')->default(false);
            $table->date('fica_verified_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->restrictOnDelete();
            $table->date('reserved_on');
            $table->date('expires_on');
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->date('deposit_received_on')->nullable();
            $table->string('status', 10)->default('active')->comment('active, converted, expired, cancelled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'expires_on']);
        });

        Schema::create('sale_agreements', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('sale_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conveyancer_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('signed_on');
            $table->decimal('purchase_price', 15, 2)->comment('As signed, incl. VAT where the seller is a VAT vendor');
            $table->boolean('vat_applies')->default(true);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->date('deposit_due_on')->nullable();
            $table->date('deposit_received_on')->nullable();
            $table->string('deposit_held_by')->nullable()->comment('Conveyancer or agency holding the deposit in trust');
            $table->string('trust_account_ref', 60)->nullable();
            $table->boolean('bond_required')->default(true);
            $table->decimal('bond_amount', 15, 2)->nullable();
            $table->string('bond_originator')->nullable();
            $table->date('occupation_date')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('commission_amount', 15, 2)->nullable();
            $table->string('commission_status', 10)->default('pending')->comment('pending, approved, paid');
            $table->string('status', 14)->default('conditional')->comment('conditional, unconditional, registered, lapsed, cancelled');
            $table->date('registered_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('sale_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->comment('bond_approval, sale_of_property, deposit, other');
            $table->string('description');
            $table->date('due_on');
            $table->string('status', 8)->default('open')->comment('open, met, waived, failed');
            $table->date('resolved_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'due_on']);
        });

        Schema::create('transfer_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('step', 30);
            $table->unsignedTinyInteger('sort');
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['sale_agreement_id', 'step']);
        });
    }

    public function down(): void
    {
        foreach (['transfer_steps', 'sale_conditions', 'sale_agreements', 'reservations', 'buyers', 'unit_prices', 'sale_units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
