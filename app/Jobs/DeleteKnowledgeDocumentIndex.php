<?php

namespace App\Jobs;

use App\Services\IntegrationUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class DeleteKnowledgeDocumentIndex implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $knowledgeDocumentId,
        public int $userId,
    ) {}

    public function handle(): void
    {
        $webhookUrl = IntegrationUrl::webhook(
            config('services.n8n.delete_webhook_url'),
            'n8n',
        );
        $internalToken = config('services.rag.internal_token');

        if (! is_string($webhookUrl) || $webhookUrl === '' || ! is_string($internalToken) || $internalToken === '') {
            return;
        }

        Http::timeout((int) config('services.n8n.timeout', 600))
            ->post($webhookUrl, [
                'token' => $internalToken,
                'document_id' => $this->knowledgeDocumentId,
                'user_id' => $this->userId,
            ])
            ->throw();
    }
}
