<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('day', 20);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['group_id', 'day', 'start_time', 'end_time'], 'group_sessions_unique_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_sessions');
    }
};
