<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
            $table->foreign('parent_id')->references('id')->on('helpdesk_categories')->nullOnDelete();
        });

        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->nullable()->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('create_request_key', 64)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('subject', 200);
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
            $table->index(['assignee_id', 'status', 'updated_at']);
            $table->index(['assignee_id', 'updated_at']);
            $table->index(['user_id', 'status', 'updated_at']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['category_id', 'created_at']);
            $table->unique(['user_id', 'create_request_key']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('helpdesk_categories')->nullOnDelete();
            $table->foreign('assignee_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('helpdesk_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_kind', 16)->default('reply');
            $table->string('request_key', 64)->nullable();
            $table->longText('body');
            $table->boolean('is_public')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'is_public', 'created_at']);
            $table->unique(['ticket_id', 'user_id', 'request_kind', 'request_key'], 'helpdesk_message_request_unique');
            $table->foreign('ticket_id')->references('id')->on('helpdesk_tickets')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('helpdesk_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
            $table->foreign('ticket_id')->references('id')->on('helpdesk_tickets')->cascadeOnDelete();
            $table->foreign('from_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('helpdesk_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event_type', 48);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
            $table->foreign('ticket_id')->references('id')->on('helpdesk_tickets')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('helpdesk_canned_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'title']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('helpdesk_settings', function (Blueprint $table) {
            $table->string('setting_key', 120)->primary();
            $table->json('setting_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_settings');
        Schema::dropIfExists('helpdesk_canned_replies');
        Schema::dropIfExists('helpdesk_events');
        Schema::dropIfExists('helpdesk_assignments');
        Schema::dropIfExists('helpdesk_messages');
        Schema::dropIfExists('helpdesk_tickets');
        Schema::dropIfExists('helpdesk_categories');
    }
};
