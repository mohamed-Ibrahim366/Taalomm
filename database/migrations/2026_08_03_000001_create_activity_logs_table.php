<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Who performed the action (null for system-generated events)
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            // Machine-readable event name: user_created, profile_updated, password_changed, etc.
            $table->string('event', 100);
            // Polymorphic subject: the model the action was performed on
            $table->nullableMorphs('subject');
            // Human-readable description
            $table->string('description')->nullable();
            // Additional structured context (old/new values, metadata, etc.)
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            // No updated_at — audit records are immutable
            $table->timestamp('created_at')->useCurrent();

            $table->index('causer_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
