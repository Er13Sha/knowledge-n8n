<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExtractionDocumentProcessorClient
{
    /** @return array<string, mixed> */
    public function extract(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Файл для извлечения не найден.');
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
            throw new RuntimeException('Не удалось прочитать файл.');
        }

        try {
            $response = Http::withHeaders(['X-Internal-Token' => $token])
                ->timeout((int) config('services.document_processor.timeout', 600))
                ->attach('document', $stream, basename($path), [
                    'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
                ])
                ->post(rtrim($url, '/').'/v1/documents/extract', [
                    'max_text_chars' => (int) config('services.extraction.max_text_chars', 200_000),
                    'max_table_rows' => (int) config('services.extraction.max_table_rows', 2_000),
                ]);
        } finally {
            fclose($stream);
        }

        if (! $response->successful()) {
            $detail = $response->json('detail');
            throw new RuntimeException('Ошибка извлечения данных: '.(is_string($detail) && $detail !== '' ? $detail : $response->body()));
        }

        $payload = $response->json();
        if (! is_array($payload) || ! isset($payload['format'], $payload['text'])) {
            throw new RuntimeException('Python-сервис вернул некорректный результат извлечения.');
        }

        return $payload;
    }
}
