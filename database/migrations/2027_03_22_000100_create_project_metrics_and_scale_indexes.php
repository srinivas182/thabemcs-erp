<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 16 (scale I): precomputed project metrics, stored critical-path results, and indexes for the
 * queries that grow with the portfolio (dashboards, lookups, registers, retention clean-up).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('budget', 15, 2)->default(0)->comment('Revised budget excl. VAT');
            $table->decimal('spent', 15, 2)->default(0)->comment('Committed plus direct costs');
            $table->decimal('used_percent', 6, 1)->nullable();
            $table->decimal('paid', 15, 2)->default(0);
            $table->unsignedInteger('high_risks')->default(0);
            $table->unsignedInteger('open_incidents')->default(0);
            $table->unsignedInteger('open_snags')->default(0);
            $table->unsignedInteger('behind_activities')->default(0);
            $table->date('forecast_finish')->nullable();
            $table->boolean('late')->default(false);
            $table->string('health', 5)->default('green')->comment('red, amber or green');
            $table->unsignedTinyInteger('severity')->default(2)->comment('0 red, 1 amber, 2 green: for sorting');
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'severity', 'used_percent']);
        });

        Schema::table('programme_activities', function (Blueprint $table) {
            $table->date('early_start')->nullable()->after('percent_complete');
            $table->date('early_finish')->nullable()->after('early_start');
            $table->integer('total_float')->nullable()->after('early_finish');
            $table->boolean('is_critical')->default(false)->after('total_float');
            $table->index(['project_id', 'is_critical']);
            $table->index(['company_id', 'early_finish']);
        });

        // Lists, lookups and registers that are filtered and sorted company-wide.
        Schema::table('projects', fn (Blueprint $t) => $t->index(['company_id', 'status', 'code']));
        Schema::table('suppliers', fn (Blueprint $t) => $t->index(['company_id', 'status', 'name']));
        Schema::table('employees', fn (Blueprint $t) => $t->index(['company_id', 'status', 'last_name']));
        Schema::table('users', fn (Blueprint $t) => $t->index(['company_id', 'is_active', 'name']));
        Schema::table('supplier_invoices', function (Blueprint $t) {
            $t->index(['project_id', 'status']);
            $t->index(['company_id', 'invoice_date']);
        });
        Schema::table('purchase_orders', fn (Blueprint $t) => $t->index(['project_id', 'status']));
        Schema::table('site_attendance', fn (Blueprint $t) => $t->index(['company_id', 'captured_at']));
        Schema::table('crew_attendance', fn (Blueprint $t) => $t->index(['company_id', 'worked_on']));
        Schema::table('notifications', fn (Blueprint $t) => $t->index(['notifiable_type', 'notifiable_id', 'read_at']));
        Schema::table('activity_log', fn (Blueprint $t) => $t->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('activity_log', fn (Blueprint $t) => $t->dropIndex(['created_at']));
        Schema::table('notifications', fn (Blueprint $t) => $t->dropIndex(['notifiable_type', 'notifiable_id', 'read_at']));
        Schema::table('crew_attendance', fn (Blueprint $t) => $t->dropIndex(['company_id', 'worked_on']));
        Schema::table('site_attendance', fn (Blueprint $t) => $t->dropIndex(['company_id', 'captured_at']));
        Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropIndex(['project_id', 'status']));
        Schema::table('supplier_invoices', function (Blueprint $t) {
            $t->dropIndex(['project_id', 'status']);
            $t->dropIndex(['company_id', 'invoice_date']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex(['company_id', 'is_active', 'name']));
        Schema::table('employees', fn (Blueprint $t) => $t->dropIndex(['company_id', 'status', 'last_name']));
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropIndex(['company_id', 'status', 'name']));
        Schema::table('projects', fn (Blueprint $t) => $t->dropIndex(['company_id', 'status', 'code']));
        Schema::table('programme_activities', function (Blueprint $t) {
            $t->dropIndex(['project_id', 'is_critical']);
            $t->dropIndex(['company_id', 'early_finish']);
            $t->dropColumn(['early_start', 'early_finish', 'total_float', 'is_critical']);
        });
        Schema::dropIfExists('project_metrics');
    }
};
