<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 13: construction programme (activities with dependencies for the Gantt chart and critical path),
 * company master data (cost code library, units), meetings with action items, crew attendance,
 * H&S legal appointments and the safety file checklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_activities', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('wbs', 20)->nullable();
            $table->string('name');
            $table->date('planned_start');
            $table->unsignedSmallInteger('duration_days')->comment('Working days; 0 = milestone');
            $table->date('actual_start')->nullable();
            $table->date('actual_finish')->nullable();
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('budget_value', 15, 2)->nullable()->comment('For earned value (Sprint 14)');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort']);
        });

        Schema::create('activity_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('predecessor_id')->constrained('programme_activities')->cascadeOnDelete();
            $table->foreignId('successor_id')->constrained('programme_activities')->cascadeOnDelete();
            $table->smallInteger('lag_days')->default(0);
            $table->timestamps();

            $table->unique(['predecessor_id', 'successor_id']);
        });

        Schema::create('cost_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('description');
            $table->string('category', 24)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name', 60);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('type', 16);
            $table->string('title');
            $table->timestamp('held_at');
            $table->string('location')->nullable();
            $table->text('attendees')->nullable();
            $table->text('apologies')->nullable();
            $table->longText('minutes')->nullable();
            $table->string('status', 12)->default('draft')->comment('draft or issued');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'type', 'number']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->timestamp('escalated_at')->nullable()->after('completed_at');
        });

        Schema::create('crew_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->date('worked_on');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('status', 12)->default('present')->comment('present, absent, sick, leave');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'worked_on']);
            $table->index(['project_id', 'worked_on']);
        });

        Schema::create('safety_appointments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('appointee_name');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('appointed_on');
            $table->date('competency_expires_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'type']);
        });

        Schema::create('safety_file_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('item', 40);
            $table->string('status', 12)->default('missing')->comment('missing, in_place, not_applicable');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->date('review_due_on')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'item']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->unique()->after('delivery_id');
            $table->string('photo_path')->nullable()->after('notes');
        });

        // Records captured offline on the site app carry the phone's id so retries never duplicate them.
        foreach (['snags', 'inspections', 'site_instructions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('client_id')->nullable()->unique()->after('id');
            });
        }

        Schema::table('site_diaries', function (Blueprint $table) {
            $table->decimal('temperature_max', 4, 1)->nullable()->after('weather');
            $table->decimal('rain_mm', 5, 1)->nullable()->after('temperature_max');
            $table->boolean('weather_auto')->default(false)->after('rain_mm');
        });
    }

    public function down(): void
    {
        Schema::table('site_diaries', fn (Blueprint $t) => $t->dropColumn(['temperature_max', 'rain_mm', 'weather_auto']));
        foreach (['snags', 'inspections', 'site_instructions'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn('client_id'));
        }
        Schema::table('goods_receipts', fn (Blueprint $t) => $t->dropColumn(['client_id', 'photo_path']));
        foreach (['safety_file_items', 'safety_appointments', 'crew_attendance'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('meeting_id');
            $t->dropColumn('escalated_at');
        });
        foreach (['meetings', 'units', 'cost_codes', 'activity_dependencies', 'programme_activities'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
