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

        if (
            $document->status === KnowledgeDocumentStatus::Indexed
            && $document->index_progress >= 100
        ) {
            return;
        }

        $document->forceFill([
            'status' => KnowledgeDocumentStatus::Processing,
            'index_progress' => 5,
            'error_message' => null,
        ])->save();

        $indexer->index($document, function (int $progress) use ($document): void {
            $document->forceFill(['index_progress' => $progress])->save();
        });

        $document->forceFill([
            'status' => KnowledgeDocumentStatus::Indexed,
            'index_progress' => 100,
            'indexed_at' => now(),
            'error_message' => null,
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
