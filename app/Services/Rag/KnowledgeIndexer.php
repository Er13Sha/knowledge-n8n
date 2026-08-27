<?php

namespace App\Services\Rag;

use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class KnowledgeIndexer
{
    public function __construct(
        private DocumentProcessorClient $documentProcessor,
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

        $preparedDocument = $this->documentProcessor->prepare($disk->path($document->path));
        $pages = $preparedDocument['pages'];
        $chunks = $preparedDocument['chunks'];

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
                    'chunk_index' => $chunk['chunk_index'],
                    'text' => $chunk['text'],
                ],
            ];
        }

        $this->vectorStore->upsert($points);

        return ['ok' => true, 'pages' => count($pages), 'chunks' => count($chunks)];
    }
}
