<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 12: standard reports emailed on a schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('report', 40);
            $table->json('filters')->nullable();
            $table->string('frequency', 8)->comment('weekly or monthly');
            $table->unsignedTinyInteger('day')->comment('1-7 (Mon-Sun) for weekly, 1-28 for monthly');
            $table->string('format', 4)->default('xlsx');
            $table->json('recipients')->comment('User ids');
            $table->timestamp('last_sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'frequency', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
