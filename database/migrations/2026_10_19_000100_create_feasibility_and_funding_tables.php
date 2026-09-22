<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5: development feasibility (scenarios with timed cost and revenue lines),
 * investor register, project funding sources, capital movements and project bank accounts.
 * All amounts are ZAR excluding VAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feasibilities', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedInteger('units')->nullable();
            $table->string('status', 16)->default('draft');
            $table->boolean('is_baseline')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'is_baseline']);
        });

        Schema::create('feasibility_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('feasibility_id')->constrained()->cascadeOnDelete();
            $table->string('category', 24);
            $table->string('description', 160);
            $table->string('basis', 24)->default('amount');
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('rate', 7, 3)->nullable()->comment('Percentage for percent-based lines');
            $table->unsignedSmallInteger('start_month')->default(1);
            $table->unsignedSmallInteger('end_month')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['feasibility_id', 'sort']);
        });

        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('entity_type', 16);
            $table->text('registration_number')->nullable()->comment('Encrypted: ID or CIPC/trust number');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamp('fica_verified_at')->nullable();
            $table->foreignId('fica_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'name']);
        });

        Schema::create('funding_sources', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->string('name');
            $table->decimal('committed_amount', 15, 2);
            $table->decimal('interest_rate', 6, 3)->nullable()->comment('Annual %, debt only');
            $table->date('agreement_signed_on')->nullable();
            $table->string('status', 16)->default('proposed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'type']);
        });

        Schema::create('funding_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('funding_source_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 8)->comment('in = contribution/drawdown, out = repayment/distribution');
            $table->decimal('amount', 15, 2);
            $table->date('occurred_on');
            $table->string('reference', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['funding_source_id', 'occurred_on']);
        });

        Schema::create('project_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank', 60);
            $table->string('account_name', 120);
            $table->char('account_last4', 4)->comment('Only the last four digits are stored');
            $table->date('opened_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bank_accounts');
        Schema::dropIfExists('funding_movements');
        Schema::dropIfExists('funding_sources');
        Schema::dropIfExists('investors');
        Schema::dropIfExists('feasibility_lines');
        Schema::dropIfExists('feasibilities');
    }
};
