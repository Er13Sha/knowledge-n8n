<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KnowledgeSearchService
{
    /**
     * @param  list<int>  $documentIds
     * @param  list<array{role: string, content: string}>  $history
     * @return array{mode: string, answer: string, sources: list<array<string, mixed>>, matches: list<array<string, mixed>>, quality?: array<string, mixed>}
     */
    public function ask(
        int $userId,
        string $question,
        ?int $documentId = null,
        string $mode = 'rag',
        array $documentIds = [],
        array $history = [],
    ): array {
        $n8nSearchWebhookUrl = IntegrationUrl::webhook(
            config('services.n8n.search_webhook_url'),
            'n8n',
        );

        if (is_string($n8nSearchWebhookUrl) && $n8nSearchWebhookUrl !== '') {
            return $this->askThroughN8n(
                $n8nSearchWebhookUrl,
                $userId,
                $question,
                $documentId,
                $mode,
                $documentIds,
                $history,
            );
        }

        throw new RuntimeException('Не настроен N8N_SEARCH_WEBHOOK_URL.');
    }

    /**
     * @param  list<int>  $documentIds
     * @param  list<array{role: string, content: string}>  $history
     * @return array{mode: string, answer: string, sources: list<array<string, mixed>>, matches: list<array<string, mixed>>, quality?: array<string, mixed>}
     */
    private function askThroughN8n(
        string $webhookUrl,
        int $userId,
        string $question,
        ?int $documentId,
        string $mode,
        array $documentIds,
        array $history,
    ): array {
        $internalToken = config('services.rag.internal_token');

        if (! is_string($internalToken) || $internalToken === '') {
            throw new RuntimeException('Не настроен RAG_INTERNAL_TOKEN.');
        }

        $response = Http::timeout((int) config('services.n8n.timeout', 600))
            ->post($webhookUrl, [
                'token' => $internalToken,
                'document_id' => $documentId,
                'user_id' => $userId,
                'question' => $question,
                'mode' => $mode,
                'document_ids' => $documentIds,
                'history' => $history,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Ошибка поиска n8n: '.$response->body());
        }

        /** @var array{mode?: string, answer?: string, sources?: list<array<string, mixed>>, matches?: list<array<string, mixed>>} $payload */
        $payload = $response->json();

        $result = [
            'mode' => (string) ($payload['mode'] ?? $mode),
            'answer' => (string) ($payload['answer'] ?? 'Ответ не найден.'),
            'sources' => $payload['sources'] ?? [],
            'matches' => $payload['matches'] ?? [],
        ];

        if (is_array($payload['quality'] ?? null)) {
            $result['quality'] = $payload['quality'];
        }

        return $result;
    }
}
