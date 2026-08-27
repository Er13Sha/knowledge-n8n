<?php

use App\Services\Rag\OllamaClient;
use App\Services\Rag\QdrantVectorStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('ollama chat receives numbered context and conversation history', function () {
    config()->set('services.ollama.url', 'http://ollama.test');
    config()->set('services.ollama.model', 'knowledge-model');
    Http::fake([
        'http://ollama.test/api/chat' => Http::response([
            'message' => ['content' => 'Требование действует с августа [1].'],
        ]),
    ]);

    $answer = app(OllamaClient::class)->answer(
        'Когда действует требование?',
        [[
            'number' => 1,
            'document_name' => 'policy.pdf',
            'page' => 3,
            'excerpt' => 'Требование действует с августа.',
        ]],
        [['role' => 'user', 'content' => 'Расскажи о политике']],
    );

    expect($answer)->toBe('Требование действует с августа [1].');

    Http::assertSent(function (Request $request): bool {
        $messages = $request['messages'];

        return $request['model'] === 'knowledge-model'
            && $request['stream'] === false
            && $messages[1]['content'] === 'Расскажи о политике'
            && str_contains($messages[2]['content'], '[1] Документ: policy.pdf; страница: 3');
    });
});

test('qdrant fulltext search uses its text index and visible document ids', function () {
    config()->set('services.qdrant.url', 'http://qdrant.test');
    config()->set('services.qdrant.collection', 'knowledge_documents');
    Http::fake([
        'http://qdrant.test/collections/knowledge_documents/points/scroll' => Http::response([
            'result' => ['points' => []],
        ]),
    ]);

    $result = app(QdrantVectorStore::class)->fullTextSearch('ионы растворе', 20, null, [10, 11]);

    expect($result)->toBe([]);

    Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'filter.must.0.key') === 'document_id'
        && data_get($request->data(), 'filter.must.0.match.any') === [10, 11]
        && data_get($request->data(), 'filter.must.1.match.text_any') === 'ионы растворе');
});

test('qdrant collection creates payload indexes required by search filters', function () {
    config()->set('services.qdrant.url', 'http://qdrant.test');
    config()->set('services.qdrant.collection', 'knowledge_documents');
    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response([
                'result' => [
                    'config' => ['params' => ['vectors' => ['size' => 2]]],
                    'payload_schema' => [],
                ],
            ]);
        }

        return Http::response(['status' => 'ok']);
    });

    app(QdrantVectorStore::class)->ensureCollection(2);

    $indexedFields = collect(Http::recorded())
        ->filter(fn (array $record): bool => str_ends_with($record[0]->url(), '/index?wait=true'))
        ->map(fn (array $record): mixed => $record[0]['field_name'])
        ->values()
        ->all();

    expect($indexedFields)->toBe(['document_id', 'user_id', 'text']);
});
