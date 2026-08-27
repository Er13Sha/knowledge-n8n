<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->string('code', 100)->primary();
            $table->string('name', 255);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        $departments = [
            ['code' => 'management', 'name' => 'Руководство'],
            ['code' => 'hr', 'name' => 'Отдел кадров'],
            ['code' => 'finance', 'name' => 'Финансовый отдел'],
            ['code' => 'legal', 'name' => 'Юридический отдел'],
            ['code' => 'it', 'name' => 'IT-отдел'],
        ];

        $existingCodes = DB::table('users')
            ->whereNotNull('department_id')
            ->pluck('department_id')
            ->merge(DB::table('knowledge')->whereNotNull('department_id')->pluck('department_id'))
            ->filter()
            ->unique()
            ->values();

        foreach ($existingCodes as $code) {
            if (! collect($departments)->contains('code', $code)) {
                $departments[] = ['code' => $code, 'name' => $code];
            }
        }

        DB::table('departments')->insert(array_map(
            fn (array $department): array => $department + [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $departments,
        ));

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('department_id')->references('code')->on('departments')->nullOnDelete();
        });

        Schema::table('knowledge', function (Blueprint $table): void {
            $table->foreign('department_id')->references('code')->on('departments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
        });

        Schema::dropIfExists('departments');
    }
};
