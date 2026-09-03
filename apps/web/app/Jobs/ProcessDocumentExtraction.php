<?php

namespace App\Jobs;

use App\Enums\DocumentExtractionStatus;
use App\Models\DocumentExtraction;
use App\Services\Extraction\DocumentExtractionAnalyzer;
use App\Services\Extraction\ExtractionDocumentProcessorClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentExtraction implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(public int $extractionId) {}

    public function handle(ExtractionDocumentProcessorClient $processor, DocumentExtractionAnalyzer $analyzer): void
    {
        $extraction = DocumentExtraction::query()->findOrFail($this->extractionId);
        $path = Storage::disk($extraction->disk)->path($extraction->path);

        $extraction->forceFill([
            'status' => DocumentExtractionStatus::Processing,
            'progress' => 10,
            'error_message' => null,
        ])->save();

        $document = $processor->extract($path);
        $extraction->forceFill([
            'detected_format' => (string) ($document['format'] ?? 'other'),
            'mime_type' => (string) ($document['mime_type'] ?? $extraction->mime_type),
            'progress' => 75,
        ])->save();

        $result = $analyzer->analyze($document);
        $extraction->forceFill([
            'status' => DocumentExtractionStatus::Completed,
            'progress' => 100,
            'result' => $result,
            'completed_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        DocumentExtraction::query()->whereKey($this->extractionId)->update([
            'status' => DocumentExtractionStatus::Failed->value,
            'error_message' => mb_substr($exception->getMessage(), 0, 2_000),
        ]);
    }
}
