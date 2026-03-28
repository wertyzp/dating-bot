<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigInteger('id')->unique();
            $table->boolean('is_bot');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('language_code')->nullable();
            $table->boolean('is_premium')->nullable();
            $table->boolean('added_to_attachment_menu')->nullable();
            $table->boolean('can_join_groups')->nullable();
            $table->boolean('can_read_all_group_messages')->nullable();
            $table->boolean('supports_inline_queries')->nullable();
            $table->timestamps();
        });

        // create table chats
        Schema::create('chats', function (Blueprint $table): void {
            $table->bigInteger('id')->unique();
            $table->string('type');
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('all_members_are_administrators')->nullable();
            $table->boolean('is_forum')->nullable();
            $table->timestamps();
        });

        // create table chat users
        Schema::create('chat_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->bigInteger('chat_id');
            $table->timestamps();
            //unique
            $table->unique(['user_id', 'chat_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->bigInteger('user_id');
            $table->bigInteger('message_id');
            $table->text('text');
            $table->jsonb('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
