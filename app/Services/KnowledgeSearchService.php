<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KnowledgeSearchService
{
    /**
     * @return array{answer: string, sources: list<array<string, mixed>>, matches: list<array<string, mixed>>}
     */
    public function ask(int $userId, string $question, ?int $documentId = null): array
    {
        $n8nSearchWebhookUrl = IntegrationUrl::webhook(
            config('services.n8n.search_webhook_url'),
            'n8n',
        );

        if (is_string($n8nSearchWebhookUrl) && $n8nSearchWebhookUrl !== '') {
            return $this->askThroughN8n($n8nSearchWebhookUrl, $userId, $question, $documentId);
        }

        throw new RuntimeException('Не настроен N8N_SEARCH_WEBHOOK_URL.');
    }

    /**
     * @return array{answer: string, sources: list<array<string, mixed>>, matches: list<array<string, mixed>>}
     */
    private function askThroughN8n(string $webhookUrl, int $userId, string $question, ?int $documentId): array
    {
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
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Ошибка поиска n8n: '.$response->body());
        }

        /** @var array{answer?: string, sources?: list<array<string, mixed>>, matches?: list<array<string, mixed>>} $payload */
        $payload = $response->json();

        return [
            'answer' => (string) ($payload['answer'] ?? 'Ответ не найден.'),
            'sources' => $payload['sources'] ?? [],
            'matches' => $payload['matches'] ?? [],
        ];
    }

}
