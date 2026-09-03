<?php

use App\Enums\KnowledgeDocumentStatus;
use App\Jobs\DeleteKnowledgeDocumentIndex;
use App\Jobs\IndexKnowledgeDocument;
use App\Models\Department;
use App\Models\Knowledge;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Rag\KnowledgeIndexer;
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

test('knowledge metadata exposes departments available for filtering', function () {
    $user = User::factory()->create(['department_id' => 'legal']);

    $this->actingAs($user)
        ->getJson(route('api.knowledge.documents.index'))
        ->assertOk()
        ->assertJsonCount(1, 'meta.filters.departments')
        ->assertJsonPath('meta.filters.departments.0.value', 'legal')
        ->assertJsonPath('meta.filters.departments.0.title', 'Юридический отдел');
});

test('knowledge metadata reads department names from the database', function () {
    Department::query()->where('code', 'legal')->update(['name' => 'Юридический департамент']);
    $user = User::factory()->create(['department_id' => 'legal']);

    $this->actingAs($user)
        ->getJson(route('api.knowledge.documents.index'))
        ->assertOk()
        ->assertJsonPath('meta.filters.departments.0.title', 'Юридический департамент');
});

test('authenticated user can filter documents and search by title', function () {
    $user = User::factory()->create(['department_id' => 'legal']);

    $matchingKnowledge = Knowledge::query()->create([
        'user_id' => $user->id,
        'department_id' => 'legal',
        'title' => 'Information security policy',
        'doc_type' => 'policy',
        'status' => 'indexed',
        'approved_at' => '2026-08-20',
    ]);
    $matchingDocument = KnowledgeDocument::factory()->for($user)->indexed()->create([
        'knowledge_id' => $matchingKnowledge->id,
        'original_name' => 'security-policy.pdf',
    ]);

    $otherKnowledge = Knowledge::query()->create([
        'user_id' => $user->id,
        'department_id' => 'legal',
        'title' => 'Information security draft',
        'doc_type' => 'instruction',
        'status' => 'indexed',
        'approved_at' => '2026-08-20',
    ]);
    $otherDocument = KnowledgeDocument::factory()->for($user)->indexed()->create([
        'knowledge_id' => $otherKnowledge->id,
        'original_name' => 'security-draft.pdf',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.knowledge.documents.index').'?search=security&department_id=legal&doc_type=policy&status=indexed')
        ->assertOk()
        ->assertJsonPath('data.0.id', $matchingDocument->id)
        ->assertJsonMissing(['id' => $otherDocument->id]);
});

test('authenticated user can upload pdf document for indexing', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create(['department_id' => 'legal']);
    $file = UploadedFile::fake()->create('policy.pdf', 128, 'application/pdf');

    $this->actingAs($user)
        ->postJson(route('api.knowledge.documents.store'), [
            'department_id' => 'legal',
            'title' => 'Политика информационной безопасности',
            'doc_type' => 'policy',
            'approved_at' => '2026-08-20',
            'document' => $file,
        ])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'policy.pdf')
        ->assertJsonPath('data.status', KnowledgeDocumentStatus::Pending->value)
        ->assertJsonPath('data.status_label', KnowledgeDocumentStatus::Pending->label())
        ->assertJsonPath('data.index_progress', 0)
        ->assertJsonPath('data.is_searchable', false)
        ->assertJsonPath('data.title', 'Политика информационной безопасности')
        ->assertJsonPath('data.department_id', 'legal')
        ->assertJsonPath('data.department_label', 'Юридический отдел')
        ->assertJsonPath('data.doc_type', 'policy')
        ->assertJsonPath('data.doc_type_label', 'Политика')
        ->assertJsonPath('data.approved_at', '2026-08-20');

    $document = KnowledgeDocument::query()->whereBelongsTo($user)->firstOrFail();

    $this->assertDatabaseHas('knowledge', [
        'id' => $document->knowledge_id,
        'user_id' => $user->id,
        'department_id' => 'legal',
        'title' => 'Политика информационной безопасности',
        'doc_type' => 'policy',
        'approved_at' => '2026-08-20 00:00:00',
    ]);

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

test('authenticated user cannot delete another user pdf document', function () {
    $user = User::factory()->create();
    $document = KnowledgeDocument::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.knowledge.documents.destroy', $document))
        ->assertNotFound();
});

test('authenticated user can search indexed document', function () {
    config()->set('services.n8n.search_webhook_url', 'https://n8n.test/webhook/search');
    config()->set('services.rag.internal_token', 'test-token');

    Http::fake([
        'https://n8n.test/webhook/search' => Http::response([
            'mode' => 'rag',
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
            'mode' => 'rag',
            'history' => [
                ['role' => 'user', 'content' => 'Предыдущий вопрос'],
                ['role' => 'assistant', 'content' => 'Предыдущий ответ'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'rag')
        ->assertJsonPath('data.answer', 'Найденный ответ')
        ->assertJsonStructure(['data' => ['interaction_id']])
        ->assertJsonPath('data.sources.0.page', 3)
        ->assertJsonPath('data.matches.0.document_name', 'policy.pdf')
        ->assertJsonPath('data.matches.0.matched_terms.0', 'требования')
        ->assertJsonPath('data.matches.0.match_type', 'exact');

    $this->assertDatabaseHas('knowledge_search_interactions', [
        'user_id' => $user->id,
        'question' => 'Что указано в документе?',
        'mode' => 'rag',
    ]);

    Http::assertSent(fn ($request) => $request['document_id'] === $document->id
        && $request['user_id'] === $user->id
        && $request['mode'] === 'rag'
        && $request['document_ids'] === [$document->id]
        && $request['history'][0]['content'] === 'Предыдущий вопрос');
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
            'mode' => 'fulltext',
        ])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Ответ по всей базе')
        ->assertJsonPath('data.sources.0.document_name', 'policy.pdf');

    Http::assertSent(fn ($request) => $request['document_id'] === null
        && $request['user_id'] === $user->id
        && $request['mode'] === 'fulltext'
        && count($request['document_ids']) === 2);
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

test('index job processes the document directly through the rag indexer', function () {
    $document = KnowledgeDocument::factory()->create([
        'path' => 'knowledge-documents/manual.pdf',
    ]);

    $this->mock(KnowledgeIndexer::class)
        ->shouldReceive('index')
        ->once()
        ->withArgs(function (KnowledgeDocument $indexedDocument, ?Closure $onProgress) use ($document): bool {
            $onProgress?->__invoke(55);

            return $indexedDocument->is($document);
        })
        ->andReturn(['ok' => true, 'pages' => 3, 'chunks' => 8]);

    (new IndexKnowledgeDocument($document->id))->handle(app(KnowledgeIndexer::class));

    expect($document->fresh())
        ->status->toBe(KnowledgeDocumentStatus::Indexed)
        ->index_progress->toBe(100)
        ->indexed_at->not->toBeNull();
});

test('index job skips an already indexed document after message redelivery', function () {
    $document = KnowledgeDocument::factory()->indexed()->create();

    $this->mock(KnowledgeIndexer::class)
        ->shouldReceive('index')
        ->never();

    (new IndexKnowledgeDocument($document->id))->handle(app(KnowledgeIndexer::class));

    expect($document->fresh())
        ->status->toBe(KnowledgeDocumentStatus::Indexed)
        ->index_progress->toBe(100);
});

test('index job records a failed rag exception', function () {
    $document = KnowledgeDocument::factory()->create([
        'path' => 'knowledge-documents/scanned.pdf',
    ]);

    $this->mock(KnowledgeIndexer::class)
        ->shouldReceive('index')
        ->once()
        ->andThrow(new RuntimeException('Для сканированных PDF требуется OCR.'));

    expect(fn () => (new IndexKnowledgeDocument($document->id))->handle(app(KnowledgeIndexer::class)))
        ->toThrow(RuntimeException::class, 'Для сканированных PDF требуется OCR.');

    expect($document->fresh())
        ->status->toBe(KnowledgeDocumentStatus::Processing)
        ->index_progress->toBe(5)
        ->error_message->toBeNull();

    (new IndexKnowledgeDocument($document->id))->failed(new RuntimeException('Для сканированных PDF требуется OCR.'));

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
        ->assertJsonPath('data.index_progress', 0)
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
