<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DocumentProcessorClient
{
    /**
     * @return array{
     *     pages: list<array{page: int, text: string, ocr_used: bool}>,
     *     chunks: list<array{page: int, chunk_index: int, text: string}>
     * }
     */
    public function prepare(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('PDF-файл не найден.');
        }

        $url = config('services.document_processor.url');
        $token = config('services.document_processor.token');

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Не настроен DOCUMENT_PROCESSOR_URL.');
        }

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Не настроен DOCUMENT_PROCESSOR_TOKEN.');
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Не удалось прочитать PDF-файл.');
        }

        try {
            $response = Http::withHeaders(['X-Internal-Token' => $token])
                ->timeout((int) config('services.document_processor.timeout', 600))
                ->attach('document', $stream, basename($path), ['Content-Type' => 'application/pdf'])
                ->post(rtrim($url, '/').'/v1/documents/prepare', [
                    'chunk_size' => (int) config('services.rag.chunk_size', 1400),
                    'chunk_overlap' => (int) config('services.rag.chunk_overlap', 200),
                    'ocr_languages' => (string) config('services.rag.ocr_languages', 'rus+eng'),
                    'ocr_dpi' => (int) config('services.rag.ocr_dpi', 200),
                ]);
        } finally {
            fclose($stream);
        }

        if (! $response->successful()) {
            $detail = $response->json('detail');

            throw new RuntimeException(
                'Ошибка подготовки документа: '.(is_string($detail) && $detail !== '' ? $detail : $response->body()),
            );
        }

        $pages = $response->json('pages');
        $chunks = $response->json('chunks');

        if (! is_array($pages) || ! is_array($chunks)) {
            throw new RuntimeException('Python-сервис вернул некорректный результат подготовки документа.');
        }

        return [
            'pages' => $this->normalizePages($pages),
            'chunks' => $this->normalizeChunks($chunks),
        ];
    }

    /**
     * @param  array<mixed>  $pages
     * @return list<array{page: int, text: string, ocr_used: bool}>
     */
    private function normalizePages(array $pages): array
    {
        return array_values(array_map(function (mixed $page): array {
            if (! is_array($page) || ! isset($page['page'], $page['text'])) {
                throw new RuntimeException('Python-сервис вернул некорректные данные страницы.');
            }

            return [
                'page' => (int) $page['page'],
                'text' => (string) $page['text'],
                'ocr_used' => (bool) ($page['ocr_used'] ?? false),
            ];
        }, $pages));
    }

    /**
     * @param  array<mixed>  $chunks
     * @return list<array{page: int, chunk_index: int, text: string}>
     */
    private function normalizeChunks(array $chunks): array
    {
        return array_values(array_map(function (mixed $chunk): array {
            if (! is_array($chunk) || ! isset($chunk['page'], $chunk['chunk_index'], $chunk['text'])) {
                throw new RuntimeException('Python-сервис вернул некорректные данные фрагмента.');
            }

            return [
                'page' => (int) $chunk['page'],
                'chunk_index' => (int) $chunk['chunk_index'],
                'text' => (string) $chunk['text'],
            ];
        }, $chunks));
    }
}
