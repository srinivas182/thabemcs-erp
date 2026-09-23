<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 21: saved report layouts, data imports with rollback, notification preferences and the daily
 * digest, and webhooks for other systems.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 60);
            $table->string('name');
            $table->json('columns')->comment('Columns to show, in order');
            $table->json('filters')->nullable()->comment('Filters applied by default');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'report_key']);
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('file_name');
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->json('errors')->nullable();
            $table->string('status', 10)->comment('checked, imported, failed');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('email_immediately')->default(true);
            $table->boolean('daily_digest')->default(false);
            $table->json('muted')->nullable()->comment('Kinds of notification the person does not want');
            $table->timestamps();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 500);
            $table->json('events');
            $table->text('secret')->comment('Encrypted: used to sign every delivery');
            $table->boolean('active')->default(true);
            $table->timestamp('last_delivered_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedInteger('failures')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_deliveries', 'webhooks', 'notification_preferences', 'import_runs', 'report_presets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
