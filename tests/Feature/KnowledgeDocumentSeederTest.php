<?php

use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\KnowledgeDocumentSeeder;

test('knowledge document seeder creates one thousand documents for the seed user', function () {
    $this->seed(KnowledgeDocumentSeeder::class);

    $seedUser = User::query()
        ->where('email', env('SEED_KNOWLEDGE_USER_EMAIL', env('ADMIN_EMAIL', 'admin@example.com')))
        ->firstOrFail();

    expect(KnowledgeDocument::query()->whereBelongsTo($seedUser)->count())->toBe(1000)
        ->and(KnowledgeDocument::query()
            ->whereBelongsTo($seedUser)
            ->where('original_name', 'test-document-0001.pdf')
            ->count())->toBe(1);
});
