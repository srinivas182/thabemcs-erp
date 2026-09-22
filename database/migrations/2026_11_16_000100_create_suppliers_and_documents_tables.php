<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7: document management (versioned files, drawing register) and the
 * contractor / supplier registry with compliance documents and performance ratings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('folder', 190)->default('General');
            $table->string('title');
            $table->string('category', 24);
            $table->string('drawing_number', 60)->nullable();
            $table->string('drawing_discipline', 40)->nullable();
            $table->json('restricted_to_roles')->nullable()->comment('Null = everyone in the company with access to the project');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'folder']);
            $table->index(['company_id', 'category']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('revision', 16)->nullable()->comment('Drawing revision, e.g. B or P3');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'version']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('trading_name')->nullable();
            $table->string('type', 24);
            $table->string('registration_number', 32)->nullable();
            $table->string('vat_number', 16)->nullable();
            $table->string('cidb_crs_number', 20)->nullable();
            $table->unsignedTinyInteger('cidb_grade')->nullable();
            $table->string('cidb_class', 8)->nullable()->comment('e.g. GB, CE, EB, ME');
            $table->string('bbbee_level', 16)->nullable()->comment('1-8 or non_compliant');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('province', 3)->nullable();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type', 'status']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('reference', 80)->nullable()->comment('PIN, certificate or policy number');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'expires_on']);
            $table->index(['supplier_id', 'type']);
        });

        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('quality');
            $table->unsignedTinyInteger('timeliness');
            $table->unsignedTinyInteger('safety');
            $table->text('comment')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
        Schema::dropIfExists('supplier_documents');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
