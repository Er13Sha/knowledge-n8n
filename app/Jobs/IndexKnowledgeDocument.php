<?php

namespace App\Jobs;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\Rag\KnowledgeIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexKnowledgeDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(public int $knowledgeDocumentId) {}

    public function handle(KnowledgeIndexer $indexer): void
    {
        $document = KnowledgeDocument::query()->findOrFail($this->knowledgeDocumentId);

        $document->forceFill([
            'status' => KnowledgeDocumentStatus::Processing,
            'error_message' => null,
        ])->save();

        try {
            $indexer->index($document);

            $document->forceFill([
                'status' => KnowledgeDocumentStatus::Indexed,
                'indexed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $document->forceFill([
                'status' => KnowledgeDocumentStatus::Failed,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
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
