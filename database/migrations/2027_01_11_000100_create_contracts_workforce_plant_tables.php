<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 11: construction contracts with payment certificates and retention; workforce
 * (employees, site allocation, leave, overtime); plant and equipment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 60);
            $table->string('contract_form', 20);
            $table->decimal('contract_sum', 15, 2)->comment('Excl. VAT');
            $table->decimal('retention_percent', 5, 2)->default(10);
            $table->decimal('retention_cap_percent', 5, 2)->nullable()->comment('Retention stops growing at this % of the contract sum');
            $table->decimal('release_at_practical_percent', 5, 2)->default(50);
            $table->date('practical_completion_on')->nullable();
            $table->date('final_completion_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['project_id', 'reference']);
        });

        Schema::create('payment_certificates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('valuation_date');
            $table->decimal('gross_value', 15, 2)->comment('Work done and materials on site to date, incl. variations, excl. VAT');
            $table->decimal('retention_held', 15, 2);
            $table->decimal('retention_released', 15, 2)->default(0);
            $table->decimal('previous_certified', 15, 2);
            $table->decimal('amount_due', 15, 2)->comment('This certificate, excl. VAT');
            $table->decimal('vat', 15, 2);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('certified_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'number']);
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('payment_certificate_id')->nullable()->after('purchase_order_id')->constrained()->nullOnDelete();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number', 20);
            $table->string('first_name');
            $table->string('last_name');
            $table->text('id_number')->nullable()->comment('Encrypted (POPIA)');
            $table->string('job_title')->nullable();
            $table->string('employment_type', 16);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->unsignedTinyInteger('days_per_week')->default(5);
            $table->string('status', 12)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'employee_number']);
        });

        Schema::create('employee_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->string('role_on_site')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'from_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);
            $table->string('status', 12)->default('pending');
            $table->string('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'type', 'from_date']);
        });

        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('worked_on');
            $table->decimal('hours', 4, 2);
            $table->decimal('rate_multiplier', 3, 2)->comment('1.5 ordinary overtime, 2.0 Sunday or public holiday');
            $table->string('reason')->nullable();
            $table->string('status', 12)->default('approved');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'worked_on']);
        });

        Schema::create('plant_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('asset_number', 30);
            $table->string('description');
            $table->string('category', 30);
            $table->string('make_model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('ownership', 8)->default('owned')->comment('owned or hired');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('hire_rate_per_day', 12, 2)->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('available');
            $table->unsignedSmallInteger('service_interval_days')->nullable();
            $table->date('next_service_on')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'asset_number']);
        });

        Schema::create('plant_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('plant_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->comment('moved, serviced, breakdown, off_hired');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('happened_on');
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['plant_item_id', 'happened_on']);
        });
    }

    public function down(): void
    {
        foreach (['plant_events', 'plant_items', 'overtime_entries', 'leave_requests', 'employee_allocations', 'employees'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_certificate_id'));
        Schema::dropIfExists('payment_certificates');
        Schema::dropIfExists('contracts');
    }
};
