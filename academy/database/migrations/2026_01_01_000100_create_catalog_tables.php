<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog & learning schema. Translatable fields are stored as JSON text
 * ({"ar": "...", "fr": "...", "en": "..."}) in LONGTEXT columns, encoded with
 * unescaped unicode so that LIKE searches work for Arabic, French and English.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        // Shared taxonomy for courses, blog posts and library resources.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('course')->index(); // course | post | resource
            $table->string('slug');
            $table->longText('name');
            $table->longText('description')->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->unique();
            $table->longText('title');
            $table->longText('subtitle')->nullable();
            $table->longText('description')->nullable();
            $table->longText('outcomes')->nullable();      // {"ar": ["...", "..."], ...}
            $table->longText('prerequisites')->nullable(); // same shape
            $table->longText('audience')->nullable();      // same shape
            $table->string('level', 20)->default('beginner'); // beginner | intermediate | advanced | all
            $table->string('language', 5)->default('ar');
            $table->string('thumbnail_path')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft | published
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('featured_order')->default(0);
            $table->boolean('requires_quiz_pass')->default(false);
            $table->longText('seo_title')->nullable();
            $table->longText('seo_description')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        // Instructors may only manage the courses they are assigned to.
        Schema::create('course_user', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'user_id']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->longText('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->longText('title');
            $table->string('type', 20)->default('video'); // video | text | quiz | exercise
            $table->longText('body')->nullable();
            $table->string('video_disk', 30)->nullable();
            $table->string('video_path')->nullable();
            $table->unsignedBigInteger('video_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_required')->default(true);
            $table->string('status', 20)->default('draft')->index(); // draft | published
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lesson_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        // Resumable chunked uploads for large lesson videos.
        Schema::create('video_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->unsignedBigInteger('total_size');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->string('status', 20)->default('uploading'); // uploading | completed | failed
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pass_score')->default(70); // percentage
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('options');        // JSON list of strings
            $table->text('correct');        // JSON list of correct option indexes
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->boolean('passed')->default(false);
            $table->text('answers')->nullable();
            $table->timestamps();
            $table->index(['quiz_id', 'user_id']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'course_id']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->boolean('is_approved')->default(false)->index();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        foreach (['reviews', 'wishlists', 'bookmarks', 'lesson_progress', 'enrollments', 'quiz_attempts',
            'quiz_questions', 'quizzes', 'video_uploads', 'lesson_attachments', 'lessons', 'sections',
            'course_user', 'courses', 'categories', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
