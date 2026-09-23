<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 18, 2)->nullable();
            $table->string('currency', 5)->default('IRT');
            $table->string('phone1', 30)->nullable();
            $table->string('phone2', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name', 150)->nullable();
            $table->string('goal', 500)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestamps();
            $table->unique(['project_id', 'number']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('number')->nullable()->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('task');
            $table->string('status', 20)->index();
            $table->string('priority', 10)->default('medium')->index();
            $table->string('title', 255);
            $table->longText('content')->nullable();
            $table->foreignId('reporter_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('story_points')->nullable();
            $table->unsignedSmallInteger('done_story_points')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('logged_minutes')->nullable();
            $table->decimal('estimated_cost', 18, 2)->nullable();
            $table->decimal('cost', 18, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->index('updated_at');
        });

        Schema::create('ticket_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->json('changes')->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('filters');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('grid_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('grid_key', 50);
            $table->json('columns');
            $table->timestamps();
            $table->unique(['user_id', 'grid_key']);
        });
    }

    public function down(): void
    {
        foreach (['grid_preferences', 'ticket_menus', 'attachments', 'ticket_comments', 'ticket_revisions', 'tickets', 'sprints', 'project_user', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
