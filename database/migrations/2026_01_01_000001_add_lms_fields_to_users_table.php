<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [ UserRole::STUDENT->value, UserRole::TEACHER->value, UserRole::ADMIN->value])->default(UserRole::STUDENT->value)->after('email');

            // $table->string('role')->default(UserRole::STUDENT->value)->after('email');
            $table->string('status')->default(UserStatus::PENDING->value)->after('role');
            $table->string('phone')->nullable()->after('status');
            $table->string('photo_path')->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('photo_path');
            $table->softDeletes(); // safe delete / archival while preserving referential integrity

            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'status', 'phone', 'photo_path', 'last_login_at']);
            $table->dropSoftDeletes();
        });
    }
};
