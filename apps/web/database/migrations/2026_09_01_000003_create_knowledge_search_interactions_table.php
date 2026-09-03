<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_search_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('scope_document_id')->nullable();
            $table->string('mode', 20)->index();
            $table->text('question');
            $table->text('answer');
            $table->string('answer_status', 32)->index();
            $table->string('confidence', 20)->index();
            $table->boolean('citations_valid')->default(false);
            $table->json('source_references')->nullable();
            $table->string('feedback_rating', 20)->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_search_interactions');
    }
};
