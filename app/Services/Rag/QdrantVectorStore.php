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

    /** @return list<array{payload?: array<string, mixed>}> */
    public function scroll(int $userId, ?int $documentId = null): array
    {
        $points = [];
        $offset = null;

        do {
            $body = [
                'filter' => $this->searchFilter($userId, $documentId),
                'limit' => (int) config('services.rag.lexical_scroll_batch_size', 256),
                'with_payload' => ['document_id', 'original_name', 'page', 'chunk_index', 'text'],
                'with_vector' => false,
            ];

            if ($offset !== null) {
                $body['offset'] = $offset;
            }

            $response = $this->request()->post($this->collectionUrl('/points/scroll'), $body);

            if ($response->notFound()) {
                return [];
            }

            $response->throw();
            $page = $response->json('result.points', []);

            if (is_array($page)) {
                array_push($points, ...$page);
            }

            $offset = $response->json('result.next_page_offset');
        } while ($offset !== null);

        return $points;
    }

    /**
     * @param  list<float>  $vector
     * @return list<array{score?: float|int, payload?: array<string, mixed>}>
     */
    public function semanticSearch(array $vector, int $userId, ?int $documentId = null): array
    {
        $response = $this->request()->post($this->collectionUrl('/points/search'), [
            'vector' => $vector,
            'filter' => $this->searchFilter($userId, $documentId),
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

    /** @return array{must: list<array{key: string, match: array{value: int}}>} */
    private function searchFilter(int $userId, ?int $documentId): array
    {
        $conditions = [
            ['key' => 'user_id', 'match' => ['value' => $userId]],
        ];

        if ($documentId !== null) {
            $conditions[] = ['key' => 'document_id', 'match' => ['value' => $documentId]];
        }

        return ['must' => $conditions];
    }
}
