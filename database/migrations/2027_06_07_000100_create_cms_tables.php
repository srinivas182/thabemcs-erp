<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24: the content management system behind the public website. Pages built from blocks, with
 * versions and publishing; a media library; menus; a blog; and forms whose submissions become enquiries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable()->comment('Describes the image for screen readers and search engines');
            $table->string('title')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('template', 20)->default('page')->comment('page, home, contact, developments');
            $table->json('blocks')->comment('The content, block by block, in order');
            $table->json('seo')->nullable()->comment('Title, description and share image');
            $table->string('status', 10)->default('draft')->comment('draft, published, archived');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('publish_from')->nullable()->comment('Set to publish it later');
            $table->boolean('is_home')->default(false);
            $table->boolean('show_in_search')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('cms_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cms_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('title');
            $table->json('blocks');
            $table->json('seo')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('saved_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['cms_page_id', 'version']);
        });

        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cms_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('excerpt', 400)->nullable();
            $table->json('blocks');
            $table->json('seo')->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('status', 10)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status', 'published_at']);
        });

        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('location', 20)->comment('primary or footer');
            $table->json('items')->comment('Label, link and children, in order');
            $table->timestamps();

            $table->unique(['company_id', 'location']);
        });

        Schema::create('cms_forms', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('fields');
            $table->json('recipients')->nullable()->comment('People told when someone submits it');
            $table->string('creates', 10)->default('none')->comment('none, buyer or tenant record');
            $table->text('success_message')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('cms_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cms_form_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('consented')->default(false)->comment('POPIA consent given on the form');
            $table->string('ip_address', 45)->nullable();
            $table->string('page', 200)->nullable()->comment('Where on the site it was sent from');
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('new')->comment('new, actioned, spam');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['cms_form_submissions', 'cms_forms', 'cms_menus', 'cms_posts', 'cms_categories',
            'cms_page_versions', 'cms_pages', 'cms_media'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
