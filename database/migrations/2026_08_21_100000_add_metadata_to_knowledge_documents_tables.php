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
        Schema::table('knowledge', function (Blueprint $table): void {
            $table->string('department_id', 100)->nullable()->after('user_id');
        });

        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->foreignId('knowledge_id')
                ->nullable()
                ->after('user_id')
                ->constrained('knowledge')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropForeign(['knowledge_id']);
            $table->dropColumn('knowledge_id');
        });

        Schema::table('knowledge', function (Blueprint $table): void {
            $table->dropColumn('department_id');
        });
    }
};
