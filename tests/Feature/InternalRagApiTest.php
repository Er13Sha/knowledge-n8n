<?php

use App\Models\KnowledgeDocument;
use App\Services\Rag\KnowledgeIndexer;
use App\Services\Rag\KnowledgeSearchEngine;
use App\Services\Rag\QdrantVectorStore;

beforeEach(function () {
    config()->set('services.rag.internal_token', 'test-rag-token');
});

test('internal rag api rejects an invalid token', function () {
    $this->postJson(route('api.internal.rag.search'), [
        'token' => 'invalid-token',
        'user_id' => 20,
        'question' => 'ионы',
    ])->assertUnauthorized()
        ->assertJsonPath('detail', 'Неверный внутренний токен RAG.');
});

test('internal rag api indexes a known document', function () {
    $document = KnowledgeDocument::factory()->create([
        'original_name' => 'document.pdf',
        'path' => 'knowledge-documents/document.pdf',
    ]);
    $this->mock(KnowledgeIndexer::class)
        ->shouldReceive('index')
        ->once()
        ->withArgs(fn (KnowledgeDocument $indexedDocument): bool => $indexedDocument->is($document))
        ->andReturn(['ok' => true, 'pages' => 3, 'chunks' => 8]);

    $this->postJson(route('api.internal.rag.index'), [
        'token' => 'test-rag-token',
        'document_id' => $document->id,
        'user_id' => $document->user_id,
        'original_name' => $document->original_name,
        'path' => $document->path,
    ])->assertOk()
        ->assertJson(['ok' => true, 'pages' => 3, 'chunks' => 8]);
});

test('internal rag api returns the indexing error details', function () {
    $document = KnowledgeDocument::factory()->create([
        'original_name' => 'document.pdf',
        'path' => 'knowledge-documents/document.pdf',
    ]);
    $this->mock(KnowledgeIndexer::class)
        ->shouldReceive('index')
        ->once()
        ->andThrow(new RuntimeException('Ollama недоступен.'));

    $this->postJson(route('api.internal.rag.index'), [
        'token' => 'test-rag-token',
        'document_id' => $document->id,
        'user_id' => $document->user_id,
        'original_name' => $document->original_name,
        'path' => $document->path,
    ])->assertStatus(502)
        ->assertJsonPath('detail', 'Индексация не выполнена: Ollama недоступен.');
});

test('internal rag api returns search matches', function () {
    $this->mock(KnowledgeSearchEngine::class)
        ->shouldReceive('search')
        ->once()
        ->with(20, 'ионы', null)
        ->andReturn([
            'answer' => 'Найдено точных совпадений: 1.',
            'sources' => [],
            'matches' => [['page' => 7, 'match_type' => 'exact']],
        ]);

    $this->postJson(route('api.internal.rag.search'), [
        'token' => 'test-rag-token',
        'user_id' => 20,
        'question' => 'ионы',
    ])->assertOk()
        ->assertJsonPath('matches.0.page', 7)
        ->assertJsonPath('matches.0.match_type', 'exact');
});

test('internal rag api deletes document vectors', function () {
    $this->mock(QdrantVectorStore::class)
        ->shouldReceive('deleteDocument')
        ->once()
        ->with(10, 20);

    $this->postJson(route('api.internal.rag.delete'), [
        'token' => 'test-rag-token',
        'document_id' => 10,
        'user_id' => 20,
    ])->assertOk()
        ->assertJson(['ok' => true]);
});
