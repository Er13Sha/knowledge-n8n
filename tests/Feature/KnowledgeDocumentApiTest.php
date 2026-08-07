<?php

use App\Enums\KnowledgeDocumentStatus;
use App\Jobs\DeleteKnowledgeDocumentIndex;
use App\Jobs\IndexKnowledgeDocument;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('guest cannot access knowledge documents api', function () {
    $this->getJson(route('api.knowledge.documents.index'))
        ->assertUnauthorized();
});

test('authenticated user can open knowledge frontend', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('knowledge'))
        ->assertOk();
});

test('authenticated user can list only own knowledge documents', function () {
    $user = User::factory()->create();
    $ownDocument = KnowledgeDocument::factory()->for($user)->indexed()->create([
        'original_name' => 'own.pdf',
    ]);

    KnowledgeDocument::factory()->indexed()->create([
        'original_name' => 'other.pdf',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.knowledge.documents.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $ownDocument->id)
        ->assertJsonPath('data.0.original_name', 'own.pdf')
        ->assertJsonMissing(['original_name' => 'other.pdf']);
});

test('authenticated user can upload pdf document for indexing', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('policy.pdf', 128, 'application/pdf');

    $this->actingAs($user)
        ->postJson(route('api.knowledge.documents.store'), [
            'document' => $file,
        ])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'policy.pdf')
        ->assertJsonPath('data.status', KnowledgeDocumentStatus::Pending->value)
        ->assertJsonPath('data.status_label', KnowledgeDocumentStatus::Pending->label())
        ->assertJsonPath('data.is_searchable', false);

    $document = KnowledgeDocument::query()->whereBelongsTo($user)->firstOrFail();

    Storage::disk('local')->assertExists($document->path);
    Queue::assertPushed(IndexKnowledgeDocument::class);
});

test('authenticated user can view own pdf document inline', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->for($user)->create([
        'disk' => 'local',
        'path' => 'knowledge-documents/manual.pdf',
        'original_name' => 'Руководство.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('local')->put($document->path, '%PDF-1.4 test content');

    $response = $this->actingAs($user)
        ->get(route('api.knowledge.documents.show', $document));

    $response
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('cache-control', 'no-store, private')
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline;')
        ->and($response->streamedContent())->toBe('%PDF-1.4 test content');
});

test('authenticated user cannot view another user pdf document', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->create([
        'disk' => 'local',
        'path' => 'knowledge-documents/private.pdf',
    ]);
    Storage::disk('local')->put($document->path, '%PDF-1.4 private');

    $this->actingAs($user)
        ->get(route('api.knowledge.documents.show', $document))
        ->assertNotFound();
});

test('authenticated user can search indexed document', function () {
    config()->set('services.n8n.search_webhook_url', 'https://n8n.test/webhook/search');
    config()->set('services.rag.internal_token', 'test-token');

    Http::fake([
        'https://n8n.test/webhook/search' => Http::response([
            'answer' => 'Найденный ответ',
            'sources' => [
                ['page' => 3],
            ],
            'matches' => [
                [
                    'document_name' => 'policy.pdf',
                    'page' => 3,
                    'excerpt' => 'В документе указаны требования безопасности.',
                    'matched_terms' => ['требования'],
                    'phrase_matched' => false,
                    'match_type' => 'exact',
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->for($user)->indexed()->create();

    $this->actingAs($user)
        ->postJson(route('api.knowledge.search'), [
            'document_id' => $document->id,
            'question' => 'Что указано в документе?',
        ])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Найденный ответ')
        ->assertJsonPath('data.sources.0.page', 3)
        ->assertJsonPath('data.matches.0.document_name', 'policy.pdf')
        ->assertJsonPath('data.matches.0.matched_terms.0', 'требования')
        ->assertJsonPath('data.matches.0.match_type', 'exact');

    Http::assertSent(fn ($request) => $request['document_id'] === $document->id
        && $request['user_id'] === $user->id);
});

test('authenticated user can search all indexed documents', function () {
    config()->set('services.n8n.search_webhook_url', 'https://n8n.test/webhook/search');
    config()->set('services.rag.internal_token', 'test-token');

    Http::fake([
        'https://n8n.test/webhook/search' => Http::response([
            'answer' => 'Ответ по всей базе',
            'sources' => [
                [
                    'document_name' => 'policy.pdf',
                    'page' => 5,
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    KnowledgeDocument::factory()->for($user)->indexed()->count(2)->create();

    $this->actingAs($user)
        ->postJson(route('api.knowledge.search'), [
            'question' => 'Что указано во всех документах?',
        ])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Ответ по всей базе')
        ->assertJsonPath('data.sources.0.document_name', 'policy.pdf');

    Http::assertSent(fn ($request) => $request['document_id'] === null
        && $request['user_id'] === $user->id);
});

test('searching all documents requires at least one indexed document', function () {
    $user = User::factory()->create();
    KnowledgeDocument::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('api.knowledge.search'), [
            'question' => 'Что указано в документах?',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('document_id');
});

test('search api returns a clear integration error', function () {
    config()->set('services.n8n.search_webhook_url', 'https://n8n.test/webhook/search');
    config()->set('services.rag.internal_token', 'test-token');

    Http::fake([
        'https://n8n.test/webhook/search' => Http::response(['detail' => 'RAG unavailable'], 503),
    ]);

    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->for($user)->indexed()->create();

    $this->actingAs($user)
        ->postJson(route('api.knowledge.search'), [
            'document_id' => $document->id,
            'question' => 'Что указано в документе?',
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Ошибка поиска n8n'));
});

test('index job sends pdf document to n8n webhook', function () {
    config()->set('services.n8n.index_webhook_url', 'https://n8n.test/webhook/index');
    config()->set('services.rag.internal_token', 'test-token');

    Http::fake([
        'https://n8n.test/webhook/index' => Http::response(['ok' => true]),
    ]);

    Storage::fake('local');

    $document = KnowledgeDocument::factory()->create([
        'path' => 'knowledge-documents/manual.pdf',
    ]);

    Storage::disk('local')->put($document->path, '%PDF-1.4');

    (new IndexKnowledgeDocument($document->id))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://n8n.test/webhook/index'
        && $request['token'] === 'test-token'
        && $request['document_id'] === $document->id
        && $request['user_id'] === $document->user_id
        && $request['path'] === $document->path);

    expect($document->fresh())
        ->status->toBe(KnowledgeDocumentStatus::Indexed)
        ->indexed_at->not->toBeNull();
});

test('index job records a failed rag response', function () {
    config()->set('services.n8n.index_webhook_url', 'https://n8n.test/webhook/index');
    config()->set('services.rag.internal_token', 'test-token');

    Http::fake([
        'https://n8n.test/webhook/index' => Http::response([
            'detail' => 'Для сканированных PDF требуется OCR.',
        ], 422),
    ]);
    Storage::fake('local');

    $document = KnowledgeDocument::factory()->create([
        'path' => 'knowledge-documents/scanned.pdf',
    ]);
    Storage::disk('local')->put($document->path, '%PDF-1.4');

    (new IndexKnowledgeDocument($document->id))->handle();

    expect($document->fresh())
        ->status->toBe(KnowledgeDocumentStatus::Failed)
        ->error_message->toBe('Для сканированных PDF требуется OCR.');
});

test('authenticated user deleting document also queues vector index cleanup', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->for($user)->indexed()->create([
        'path' => 'knowledge-documents/delete.pdf',
    ]);
    Storage::disk('local')->put($document->path, '%PDF-1.4');

    $this->actingAs($user)
        ->deleteJson(route('api.knowledge.documents.destroy', $document))
        ->assertNoContent();

    Storage::disk('local')->assertMissing($document->path);
    $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
    Queue::assertPushed(
        DeleteKnowledgeDocumentIndex::class,
        fn (DeleteKnowledgeDocumentIndex $job) => $job->knowledgeDocumentId === $document->id
            && $job->userId === $user->id,
    );
});

test('authenticated user can retry failed document indexing', function () {
    Queue::fake();

    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->for($user)->create([
        'status' => KnowledgeDocumentStatus::Failed,
        'error_message' => 'Old error',
    ]);

    $this->actingAs($user)
        ->postJson(route('api.knowledge.documents.retry-indexing', $document))
        ->assertOk()
        ->assertJsonPath('data.status', KnowledgeDocumentStatus::Pending->value)
        ->assertJsonPath('data.error_message', null);

    Queue::assertPushed(IndexKnowledgeDocument::class);
});

test('authenticated user cannot search another user document', function () {
    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->indexed()->create();

    $this->actingAs($user)
        ->postJson(route('api.knowledge.search'), [
            'document_id' => $document->id,
            'question' => 'Что указано в документе?',
        ])
        ->assertNotFound();
});
