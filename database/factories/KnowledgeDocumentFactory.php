<?php

namespace Database\Factories;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => fake()->words(3, true).'.pdf',
            'disk' => 'local',
            'path' => 'knowledge-documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'status' => KnowledgeDocumentStatus::Pending,
            'index_progress' => 0,
            'indexed_at' => null,
            'error_message' => null,
        ];
    }

    public function indexed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KnowledgeDocumentStatus::Indexed,
            'index_progress' => 100,
            'indexed_at' => now(),
            'error_message' => null,
        ]);
    }
}
