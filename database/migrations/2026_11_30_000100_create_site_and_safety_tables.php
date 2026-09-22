<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 8: site diary, attendance, photos, deliveries, site instructions, snags,
 * inspections, safety incidents and toolbox talks.
 *
 * Records captured offline carry a client_id (UUID from the phone) so a retried sync never duplicates them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('geofence_radius_m')->default(300)->after('longitude');
        });

        Schema::create('site_diaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->date('diary_date');
            $table->string('weather', 16);
            $table->unsignedInteger('workers_on_site');
            $table->text('work_completed');
            $table->text('delays')->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'diary_date']);
        });

        Schema::create('site_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->string('direction', 3)->comment('in or out');
            $table->timestamp('captured_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->unsignedInteger('distance_m')->nullable()->comment('Distance from the site point');
            $table->boolean('within_geofence')->nullable();
            $table->string('selfie_path')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'captured_at']);
            $table->index(['user_id', 'captured_at']);
        });

        Schema::create('site_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->nullableMorphs('attachable');
            $table->string('disk', 32);
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('caption')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'captured_at']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('delivery_note_number', 60)->nullable();
            $table->text('items');
            $table->string('condition', 12)->default('good')->comment('good, damaged, short');
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'received_at']);
        });

        Schema::create('site_instructions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('instruction');
            $table->boolean('cost_implication')->default(false);
            $table->boolean('time_implication')->default(false);
            $table->string('status', 16)->default('issued')->comment('issued, acknowledged, closed');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->comment('quality or safety');
            $table->string('title');
            $table->string('location')->nullable();
            $table->string('result', 12)->comment('pass, fail, partial');
            $table->text('findings')->nullable();
            $table->date('inspected_on');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'kind', 'inspected_on']);
        });

        Schema::create('snags', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('description');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 12)->default('open')->comment('open, fixed, verified');
            $table->timestamp('fixed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->string('type', 20)->comment('near_miss, first_aid, medical, lost_time, dangerous_occurrence, property_damage, fatality');
            $table->timestamp('occurred_at');
            $table->string('location')->nullable();
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->string('person_involved')->nullable();
            $table->boolean('reportable')->default(false)->comment('Section 24 OHS Act / COIDA report required');
            $table->timestamp('reported_to_authority_at')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->string('status', 12)->default('open')->comment('open, investigating, closed');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'occurred_at']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('toolbox_talks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->date('held_on');
            $table->unsignedInteger('attendees');
            $table->string('presenter')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'held_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toolbox_talks');
        Schema::dropIfExists('safety_incidents');
        Schema::dropIfExists('snags');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('site_instructions');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('site_photos');
        Schema::dropIfExists('site_attendance');
        Schema::dropIfExists('site_diaries');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('geofence_radius_m'));
    }
};
