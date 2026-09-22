<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 6: land pipeline and due diligence, statutory applications, professional team and fee claims.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('land_parcels', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('property_description')->nullable()->comment('e.g. Erf 1234 Ballito, or Portion 5 of Farm X');
            $table->string('title_deed_number', 40)->nullable();
            $table->string('province', 3)->nullable();
            $table->string('town')->nullable();
            $table->decimal('size_m2', 14, 2)->nullable();
            $table->string('current_zoning')->nullable();
            $table->string('seller_name')->nullable();
            $table->decimal('asking_price', 15, 2)->nullable();
            $table->decimal('offer_price', 15, 2)->nullable();
            $table->string('status', 16)->default('identified');
            $table->date('offer_date')->nullable();
            $table->date('acceptance_date')->nullable();
            $table->date('transfer_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        Schema::create('land_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('land_parcel_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32);
            $table->string('title');
            $table->boolean('is_required')->default(true);
            $table->string('result', 12)->default('pending')->comment('pending, clear, issue, not_applicable');
            $table->text('notes')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['land_parcel_id', 'key']);
        });

        Schema::create('statutory_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('description')->nullable();
            $table->string('authority')->nullable()->comment('e.g. eThekwini Municipality, DFFE, NHBRC');
            $table->string('reference_number', 60)->nullable();
            $table->string('status', 16)->default('preparing');
            $table->date('submitted_on')->nullable();
            $table->date('expected_decision_on')->nullable();
            $table->date('decision_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('conditions')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'valid_until']);
            $table->index(['project_id', 'type']);
        });

        Schema::create('professional_appointments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('discipline', 32);
            $table->string('firm_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('registration_body', 16)->nullable();
            $table->string('registration_number', 40)->nullable();
            $table->timestamp('registration_verified_at')->nullable();
            $table->foreignId('registration_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fee_basis', 16);
            $table->decimal('fee_percentage', 6, 3)->nullable();
            $table->decimal('agreed_fee', 15, 2)->nullable()->comment('ZAR excl. VAT');
            $table->date('appointed_on')->nullable();
            $table->string('status', 16)->default('proposed');
            $table->timestamps();

            $table->index(['project_id', 'discipline']);
        });

        Schema::create('fee_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_appointment_id')->constrained()->cascadeOnDelete();
            $table->string('claim_number', 40);
            $table->string('description')->nullable()->comment('Work stage claimed, e.g. Stage 3 design development');
            $table->decimal('amount', 15, 2)->comment('ZAR excl. VAT');
            $table->date('submitted_on');
            $table->string('status', 16)->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->unique(['professional_appointment_id', 'claim_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_claims');
        Schema::dropIfExists('professional_appointments');
        Schema::dropIfExists('statutory_applications');
        Schema::dropIfExists('land_checks');
        Schema::dropIfExists('land_parcels');
    }
};
