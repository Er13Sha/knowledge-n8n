<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('department_id', 100)->nullable()->after('email');
            $table->boolean('is_super_admin')->default(false)->after('department_id');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

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
    }

    public function down(): void
    {
        Schema::dropIfExists('department_permissions');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['department_id', 'is_super_admin']);
        });
    }
};
