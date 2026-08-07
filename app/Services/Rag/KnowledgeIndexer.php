<?php

namespace App\Services\Rag;

use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class KnowledgeIndexer
{
    public function __construct(
        private PdfTextExtractor $pdfTextExtractor,
        private TextProcessor $textProcessor,
        private OllamaClient $ollamaClient,
        private QdrantVectorStore $vectorStore,
    ) {}

    /** @return array{ok: true, pages: int, chunks: int} */
    public function index(KnowledgeDocument $document): array
    {
        $disk = Storage::disk($document->disk);

        if (! $disk->exists($document->path)) {
            throw new RuntimeException('PDF-файл не найден в хранилище.');
        }

        $pages = $this->pdfTextExtractor->extractPages($disk->path($document->path));
        $chunks = [];

        foreach ($pages as $pageIndex => $pageText) {
            foreach ($this->textProcessor->split(
                $pageText,
                (int) config('services.rag.chunk_size', 1400),
                (int) config('services.rag.chunk_overlap', 200),
            ) as $pageChunk) {
                $chunks[] = ['page' => $pageIndex + 1, 'text' => $pageChunk];
            }
        }

        if ($chunks === []) {
            throw new RuntimeException('В PDF нет текста, который удалось извлечь или распознать.');
        }

        $vectors = $this->ollamaClient->embed(array_column($chunks, 'text'));
        $this->vectorStore->ensureCollection(count($vectors[0]));
        $this->vectorStore->deleteDocument($document->id, $document->user_id);
        $points = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $points[] = [
                'id' => (string) Str::uuid(),
                'vector' => $vectors[$chunkIndex],
                'payload' => [
                    'document_id' => $document->id,
                    'user_id' => $document->user_id,
                    'original_name' => $document->original_name,
                    'page' => $chunk['page'],
                    'chunk_index' => $chunkIndex,
                    'text' => $chunk['text'],
                ],
            ];
        }

        $this->vectorStore->upsert($points);

        return ['ok' => true, 'pages' => count($pages), 'chunks' => count($chunks)];
    }
}
