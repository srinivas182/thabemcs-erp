<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 10: project budgets (cost codes), variation orders, supplier invoices with
 * three-way matching, and payment runs. Amounts in ZAR; budgets are excl. VAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('description');
            $table->string('category', 24)->nullable();
            $table->decimal('original_amount', 15, 2)->default(0);
            $table->string('alert_level', 8)->nullable()->comment('Last warning sent: 80, 90 or 100');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'code']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('budget_line_id')->nullable()->after('requisition_id')->constrained()->nullOnDelete();
        });

        Schema::create('variation_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_instruction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reason', 24);
            $table->decimal('amount', 15, 2)->comment('Positive adds to the budget, negative is a saving');
            $table->integer('time_impact_days')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::create('payment_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->date('pay_on');
            $table->string('status', 20)->default('draft');
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 60);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('vat', 15, 2);
            $table->decimal('total', 15, 2);
            $table->string('status', 16)->default('captured');
            $table->json('match_issues')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('captured_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'invoice_number']);
            $table->index(['company_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('payment_runs');
        Schema::dropIfExists('variation_orders');
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_line_id');
        });
        Schema::dropIfExists('budget_lines');
    }
};
