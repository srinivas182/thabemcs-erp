<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 14: RFQ invitations to suppliers, contract form terms, earned-value snapshots,
 * workforce allowances and documents, POPIA retention rules and data subject requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique()->comment('SHA-256 of the link token; the token itself is only in the email');
            $table->date('closes_on');
            $table->text('message')->nullable();
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->unique(['requisition_id', 'supplier_id']);
        });

        Schema::table('requisition_quotes', function (Blueprint $table) {
            $table->boolean('submitted_by_supplier')->default(false)->after('notes');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('release_at_practical_percent');
            $table->unsignedSmallInteger('defects_period_months')->nullable()->after('payment_terms_days');
        });

        Schema::create('progress_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('taken_on');
            $table->decimal('planned_value', 15, 2);
            $table->decimal('earned_value', 15, 2);
            $table->decimal('actual_cost', 15, 2);
            $table->timestamps();

            $table->unique(['project_id', 'taken_on']);
        });

        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 10, 2);
            $table->string('frequency', 8)->comment('day, month or once');
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expires_on')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
        });

        Schema::create('retention_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('record', 32);
            $table->unsignedSmallInteger('keep_months');
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_run_count')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'record']);
        });

        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('type', 16)->comment('access, correction, deletion, objection');
            $table->string('subject_type', 12)->comment('employee, user, investor, other');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('details')->nullable();
            $table->date('received_on');
            $table->date('due_on');
            $table->string('status', 12)->default('open')->comment('open, in_progress, completed, refused');
            $table->text('outcome')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        foreach (['data_subject_requests', 'retention_rules', 'employee_documents', 'employee_allowances', 'progress_snapshots'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('contracts', fn (Blueprint $t) => $t->dropColumn(['payment_terms_days', 'defects_period_months']));
        Schema::table('requisition_quotes', fn (Blueprint $t) => $t->dropColumn('submitted_by_supplier'));
        Schema::dropIfExists('rfq_invitations');
    }
};
