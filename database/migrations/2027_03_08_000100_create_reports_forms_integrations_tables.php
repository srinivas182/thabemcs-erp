<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 15: saved custom reports, form templates and submissions (form builder),
 * and accounting / payroll integrations (Sage Business Cloud Accounting ZA, SimplePay).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('dataset', 32);
            $table->json('config')->comment('columns, filters, sort, totals, subtitle');
            $table->boolean('shared')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 12)->comment('quality, safety, checklist');
            $table->json('fields');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('template_version');
            $table->uuid('client_id')->unique();
            $table->json('fields')->comment('Copy of the template fields at the time, so old answers stay readable');
            $table->json('answers');
            $table->boolean('passed')->nullable()->comment('False if any pass/fail question failed');
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'submitted_at']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->boolean('enabled')->default(false);
            $table->text('credentials')->nullable()->comment('Encrypted JSON');
            $table->json('settings')->nullable()->comment('Account, tax type and item mappings');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider']);
        });

        Schema::create('integration_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('status', 8)->comment('sent or failed');
            $table->string('external_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'entity_type', 'entity_id']);
            $table->index(['company_id', 'provider', 'status']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('accounting_ref', 40)->nullable()->after('bbbee_level')->comment('Supplier ID in the accounting system');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->string('payroll_ref', 40)->nullable()->after('employee_number')->comment('Employee ID in the payroll system');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('payroll_ref'));
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('accounting_ref'));
        foreach (['integration_syncs', 'integrations', 'form_submissions', 'form_templates', 'custom_reports'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
