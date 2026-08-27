<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('scope', 20)->default('global')->after('is_system');
        });

        Schema::dropIfExists('department_permissions');
        Schema::dropIfExists('user_permissions');
    }

    public function down(): void
    {
        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'user_id']);
        });

        Schema::create('department_permissions', function (Blueprint $table): void {
            $table->string('department_id', 100);
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['department_id', 'permission_id']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('scope');
        });
    }
};
