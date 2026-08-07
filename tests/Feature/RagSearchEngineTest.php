<?php

use App\Services\Rag\KnowledgeSearchEngine;
use App\Services\Rag\OllamaClient;
use App\Services\Rag\QdrantVectorStore;
use App\Services\Rag\TextProcessor;

test('single word search uses exact matching without embeddings', function () {
    $vectorStore = Mockery::mock(QdrantVectorStore::class);
    $vectorStore->shouldReceive('scroll')->once()->with(20, null)->andReturn([
        ['payload' => ['document_id' => 10, 'original_name' => 'document.pdf', 'page' => 7, 'text' => 'Ионы находятся в растворе.']],
    ]);
    $ollamaClient = Mockery::mock(OllamaClient::class);
    $ollamaClient->shouldNotReceive('embed');
    $engine = new KnowledgeSearchEngine(new TextProcessor, $ollamaClient, $vectorStore);

    $result = $engine->search(20, 'ионы');

    expect($result['matches'])->toHaveCount(1)
        ->and($result['matches'][0]['page'])->toBe(7)
        ->and($result['matches'][0]['match_type'])->toBe('exact');
});

test('question search uses semantic vectors without generating an answer', function () {
    $ollamaClient = Mockery::mock(OllamaClient::class);
    $ollamaClient->shouldReceive('embed')->once()->with(['ионы - это'])->andReturn([[0.1, 0.2]]);
    $vectorStore = Mockery::mock(QdrantVectorStore::class);
    $vectorStore->shouldReceive('semanticSearch')->once()->with([0.1, 0.2], 20, null)->andReturn([
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

    $result = $engine->search(20, 'ионы - это');

    expect($result['matches'][0]['excerpt'])->toBe('Ионы — электрически заряженные частицы.')
        ->and($result['matches'][0]['match_type'])->toBe('semantic')
        ->and($result['matches'][0]['score'])->toBe(0.91);
});
