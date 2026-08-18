<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_sessions') && ! Schema::hasTable('group_schedules')) {
            Schema::rename('group_sessions', 'group_schedules');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_schedules') && ! Schema::hasTable('group_sessions')) {
            Schema::rename('group_schedules', 'group_sessions');
        }
    }
};
