<?php

use App\Services\Rag\KnowledgeSearchEngine;
use App\Services\Rag\OllamaClient;
use App\Services\Rag\QdrantVectorStore;
use App\Services\Rag\TextProcessor;

test('fulltext search uses exact matching for a multiword query without embeddings', function () {
    $vectorStore = Mockery::mock(QdrantVectorStore::class);
    $vectorStore->shouldReceive('fullTextSearch')->once()->with('ионы растворе', 20, null, [10])->andReturn([
        ['payload' => ['document_id' => 10, 'original_name' => 'document.pdf', 'page' => 7, 'text' => 'Ионы находятся в растворе.']],
    ]);
    $ollamaClient = Mockery::mock(OllamaClient::class);
    $ollamaClient->shouldNotReceive('embed');
    $ollamaClient->shouldNotReceive('answer');
    $engine = new KnowledgeSearchEngine(new TextProcessor, $ollamaClient, $vectorStore);

    $result = $engine->search(20, 'ионы в растворе', null, 'fulltext', [10]);

    expect($result['mode'])->toBe('fulltext')
        ->and($result['matches'])->toHaveCount(1)
        ->and($result['matches'][0]['page'])->toBe(7)
        ->and($result['matches'][0]['match_type'])->toBe('exact')
        ->and($result['sources'][0]['number'])->toBe(1);
});

test('rag search generates an answer grounded in numbered sources', function () {
    $ollamaClient = Mockery::mock(OllamaClient::class);
    $ollamaClient->shouldReceive('embed')
        ->once()
        ->with(["Расскажи о химии\nионы - это"])
        ->andReturn([[0.1, 0.2]]);
    $ollamaClient->shouldReceive('answer')
        ->once()
        ->withArgs(fn (string $question, array $sources, array $history): bool => $question === 'ионы - это'
            && $sources[0]['number'] === 1
            && $sources[0]['page'] === 12
            && $history === [['role' => 'user', 'content' => 'Расскажи о химии']])
        ->andReturn('Ионы — заряженные частицы [1].');
    $vectorStore = Mockery::mock(QdrantVectorStore::class);
    $vectorStore->shouldReceive('semanticSearch')->once()->with([0.1, 0.2], 20, null, [10])->andReturn([
        [
            'score' => 0.91,
            'payload' => [
                'document_id' => 10,
                'original_name' => 'chemistry.pdf',
                'page' => 12,
                'text' => 'Ионы — электрически заряженные частицы.',
            ],
        ],
    ]);
    $engine = new KnowledgeSearchEngine(new TextProcessor, $ollamaClient, $vectorStore);

    $result = $engine->search(
        20,
        'ионы - это',
        null,
        'rag',
        [10],
        [['role' => 'user', 'content' => 'Расскажи о химии']],
    );

    expect($result['mode'])->toBe('rag')
        ->and($result['answer'])->toBe('Ионы — заряженные частицы [1].')
        ->and($result['sources'][0]['document_name'])->toBe('chemistry.pdf')
        ->and($result['matches'][0]['excerpt'])->toBe('Ионы — электрически заряженные частицы.')
        ->and($result['matches'][0]['match_type'])->toBe('semantic')
        ->and($result['matches'][0]['score'])->toBe(0.91);
});

test('rag search does not call chat model when no sources are found', function () {
    $ollamaClient = Mockery::mock(OllamaClient::class);
    $ollamaClient->shouldReceive('embed')->once()->andReturn([[0.1, 0.2]]);
    $ollamaClient->shouldNotReceive('answer');
    $vectorStore = Mockery::mock(QdrantVectorStore::class);
    $vectorStore->shouldReceive('semanticSearch')->once()->andReturn([]);
    $engine = new KnowledgeSearchEngine(new TextProcessor, $ollamaClient, $vectorStore);

    $result = $engine->search(20, 'неизвестный вопрос');

    expect($result['sources'])->toBe([])
        ->and($result['answer'])->toContain('не найдено достаточно данных');
});
