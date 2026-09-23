<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 20: closing a project out, paying investors their returns through a waterfall, and tracking
 * capital reinvested into the next project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closeout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label');
            $table->string('group', 30);
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('sort');
            $table->date('completed_on')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });

        // Investor terms that drive the waterfall.
        Schema::table('funding_sources', function (Blueprint $table) {
            $table->decimal('preferred_return_percent', 5, 2)->nullable()->after('interest_rate')->comment('Preferred return a year, before profit share');
            $table->decimal('profit_share_percent', 5, 2)->nullable()->after('preferred_return_percent')->comment('Share of the remaining profit; pro rata to capital when blank');
        });

        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('declared_on');
            $table->decimal('amount', 15, 2)->comment('Cash being distributed');
            $table->string('status', 10)->default('draft')->comment('draft, approved, paid');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('distribution_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funding_source_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('capital', 15, 2)->default(0);
            $table->decimal('preferred', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('reference', 60)->nullable();
            $table->timestamps();

            $table->unique(['distribution_id', 'funding_source_id']);
        });

        Schema::create('reinvestments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_id')->constrained()->restrictOnDelete();
            $table->foreignId('distribution_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('to_funding_source_id')->constrained('funding_sources')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'investor_id']);
        });
    }

    public function down(): void
    {
        foreach (['reinvestments', 'distribution_lines', 'distributions', 'closeout_items'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('funding_sources', fn (Blueprint $t) => $t->dropColumn(['preferred_return_percent', 'profit_share_percent']));
    }
};
