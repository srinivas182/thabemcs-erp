<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4: stage-gate checklists and transitions, milestones, tasks, risks and issues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_gate_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 16);
            $table->string('title');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'stage', 'sort']);
        });

        Schema::create('stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 16);
            $table->string('to_stage', 16);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('stage', 16)->nullable();
            $table->date('planned_date');
            $table->date('forecast_date')->nullable();
            $table->date('completed_on')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'planned_date']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 8)->default('normal');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'assignee_id', 'status', 'due_date']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('risk')->comment('risk or issue');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('likelihood')->default(3)->comment('1-5');
            $table->unsignedTinyInteger('impact')->default(3)->comment('1-5');
            $table->text('mitigation')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->date('review_date')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('stage_transitions');
        Schema::dropIfExists('stage_gate_items');
    }
};
