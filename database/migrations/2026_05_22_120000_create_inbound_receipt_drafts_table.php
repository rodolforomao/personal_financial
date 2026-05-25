<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_receipt_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('chat_id', 64);
            $table->string('message_id', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('extracted');
            $table->string('storage_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['channel', 'chat_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_receipt_drafts');
    }
};
