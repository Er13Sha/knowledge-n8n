<?php

namespace App\Jobs;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\IntegrationUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class IndexKnowledgeDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $knowledgeDocumentId) {}

    public function handle(): void
    {
        $document = KnowledgeDocument::query()->findOrFail($this->knowledgeDocumentId);

        $document->forceFill([
            'status' => KnowledgeDocumentStatus::Processing,
            'error_message' => null,
        ])->save();

        $webhookUrl = IntegrationUrl::webhook(
            config('services.n8n.index_webhook_url'),
            'n8n',
        );

        $internalToken = config('services.rag.internal_token');

        if (! is_string($webhookUrl) || $webhookUrl === '' || ! is_string($internalToken) || $internalToken === '') {
            $document->forceFill([
                'status' => KnowledgeDocumentStatus::Failed,
                'error_message' => 'Не настроен адрес индексации n8n или внутренний токен RAG.',
            ])->save();

            return;
        }

        if (! Storage::disk($document->disk)->exists($document->path)) {
            $document->forceFill([
                'status' => KnowledgeDocumentStatus::Failed,
                'error_message' => 'PDF-файл не найден в хранилище.',
            ])->save();

            return;
        }

        $response = Http::timeout((int) config('services.n8n.timeout', 600))
            ->post($webhookUrl, [
                'token' => $internalToken,
                'document_id' => $document->id,
                'user_id' => $document->user_id,
                'original_name' => $document->original_name,
                'path' => $document->path,
            ]);

        if ($response->successful() && $response->json('ok') === true) {
            $document->forceFill([
                'status' => KnowledgeDocumentStatus::Indexed,
                'indexed_at' => now(),
                'error_message' => null,
            ])->save();

            return;
        }

        $document->forceFill([
            'status' => KnowledgeDocumentStatus::Failed,
            'error_message' => Str::limit($response->json('detail') ?: $response->body(), 2000),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        KnowledgeDocument::query()
            ->whereKey($this->knowledgeDocumentId)
            ->update([
                'status' => KnowledgeDocumentStatus::Failed->value,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
