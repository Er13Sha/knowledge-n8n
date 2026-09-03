<?php

namespace App\Services\Rag;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QdrantVectorStore
{
    public function ensureCollection(int $vectorSize): void
    {
        $response = $this->request()->get($this->collectionUrl());

        if ($response->notFound()) {
            $this->request()->put($this->collectionUrl(), [
                'vectors' => ['size' => $vectorSize, 'distance' => 'Cosine'],
            ])->throw();

            $this->ensurePayloadIndexes([]);

            return;
        }

        $response->throw();
        $existingSize = $response->json('result.config.params.vectors.size');

        if ($existingSize !== null && (int) $existingSize !== $vectorSize) {
            throw new RuntimeException(sprintf(
                'Коллекция Qdrant использует векторы размерности %d, а модель %s вернула %d.',
                (int) $existingSize,
                (string) config('services.ollama.embedding_model'),
                $vectorSize,
            ));
        }

        $payloadSchema = $response->json('result.payload_schema', []);
        $this->ensurePayloadIndexes(is_array($payloadSchema) ? $payloadSchema : []);
    }

    public function deleteDocument(int $documentId, int $userId): void
    {
        $response = $this->request()->post($this->collectionUrl('/points/delete?wait=true'), [
            'filter' => $this->documentFilter($documentId, $userId),
        ]);

        if (! $response->notFound()) {
            $response->throw();
        }
    }

    /** @param list<array<string, mixed>> $points */
    public function upsert(array $points): void
    {
        foreach (array_chunk($points, 64) as $batch) {
            $this->request()->put($this->collectionUrl('/points?wait=true'), [
                'points' => $batch,
            ])->throw();
        }
    }

    /**
     * @param  list<int>  $documentIds
     * @return list<array{payload?: array<string, mixed>}>
     */
    public function fullTextSearch(
        string $query,
        int $userId,
        ?int $documentId = null,
        array $documentIds = [],
    ): array {
        $filter = $this->searchFilter($userId, $documentId, $documentIds);
        $filter['must'][] = [
            'key' => 'text',
            'match' => ['text_any' => $query],
        ];

        $response = $this->request()->post($this->collectionUrl('/points/scroll'), [
            'filter' => $filter,
            'limit' => (int) config('services.rag.lexical_candidate_limit', 1000),
            'with_payload' => ['document_id', 'original_name', 'page', 'chunk_index', 'text'],
            'with_vector' => false,
        ]);

        if ($response->notFound()) {
            return [];
        }

        $response->throw();
        $points = $response->json('result.points', []);

        return is_array($points) ? array_values($points) : [];
    }

    /**
     * @param  list<float>  $vector
     * @return list<array{score?: float|int, payload?: array<string, mixed>}>
     */
    public function semanticSearch(array $vector, int $userId, ?int $documentId = null, array $documentIds = []): array
    {
        $response = $this->request()->post($this->collectionUrl('/points/search'), [
            'vector' => $vector,
            'filter' => $this->searchFilter($userId, $documentId, $documentIds),
            'limit' => (int) config('services.rag.top_k', 6),
            'score_threshold' => (float) config('services.rag.score_threshold', 0.25),
            'with_payload' => true,
        ]);

        if ($response->notFound()) {
            return [];
        }

        $response->throw();
        $points = $response->json('result', []);

        if (! is_array($points)) {
            return [];
        }

        /** @var list<array{score?: float|int, payload?: array<string, mixed>}> $points */
        return $points;
    }

    private function request(): PendingRequest
    {
        $apiKey = config('services.qdrant.api_key');
        $headers = is_string($apiKey) && $apiKey !== '' ? ['api-key' => $apiKey] : [];

        return Http::withHeaders($headers)->timeout((int) config('services.rag.request_timeout', 600));
    }

    private function collectionUrl(string $suffix = ''): string
    {
        return rtrim((string) config('services.qdrant.url'), '/')
            .'/collections/'.rawurlencode((string) config('services.qdrant.collection'))
            .$suffix;
    }

    /** @param array<string, mixed> $payloadSchema */
    private function ensurePayloadIndexes(array $payloadSchema): void
    {
        $indexes = [
            'document_id' => 'integer',
            'user_id' => 'integer',
            'text' => [
                'type' => 'text',
                'tokenizer' => 'word',
                'min_token_len' => 2,
                'max_token_len' => 40,
                'lowercase' => true,
                'phrase_matching' => true,
            ],
        ];

        foreach ($indexes as $fieldName => $fieldSchema) {
            if (array_key_exists($fieldName, $payloadSchema)) {
                continue;
            }

            $this->request()->put($this->collectionUrl('/index?wait=true'), [
                'field_name' => $fieldName,
                'field_schema' => $fieldSchema,
            ])->throw();
        }
    }

    /** @return array{must: list<array{key: string, match: array{value: int}}>} */
    private function documentFilter(int $documentId, int $userId): array
    {
        return [
            'must' => [
                ['key' => 'document_id', 'match' => ['value' => $documentId]],
                ['key' => 'user_id', 'match' => ['value' => $userId]],
            ],
        ];
    }

    /**
     * @param  list<int>  $documentIds
     * @return array{must: list<array<string, mixed>>}
     */
    private function searchFilter(int $userId, ?int $documentId, array $documentIds): array
    {
        if ($documentId !== null) {
            return ['must' => [
                ['key' => 'document_id', 'match' => ['value' => $documentId]],
            ]];
        }

        if ($documentIds !== []) {
            return ['must' => [
                ['key' => 'document_id', 'match' => ['any' => array_values($documentIds)]],
            ]];
        }

        return ['must' => [
            ['key' => 'user_id', 'match' => ['value' => $userId]],
        ]];
    }
}
