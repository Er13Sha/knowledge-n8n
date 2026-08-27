<?php

use App\Services\Rag\DocumentProcessorClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('document processor client sends pdf and returns prepared chunks', function () {
    config()->set('services.document_processor.url', 'http://processor.test');
    config()->set('services.document_processor.token', 'processor-token');
    config()->set('services.rag.chunk_size', 1200);
    config()->set('services.rag.chunk_overlap', 150);
    Http::fake([
        'http://processor.test/v1/documents/prepare' => Http::response([
            'pages' => [
                ['page' => 1, 'text' => 'Prepared page', 'ocr_used' => false],
            ],
            'chunks' => [
                ['page' => 1, 'chunk_index' => 0, 'text' => 'Prepared chunk'],
            ],
        ]),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'knowledge-pdf-');
    file_put_contents($path, '%PDF-test');

    try {
        $result = app(DocumentProcessorClient::class)->prepare($path);
    } finally {
        @unlink($path);
    }

    expect($result['pages'][0]['text'])->toBe('Prepared page')
        ->and($result['chunks'][0]['chunk_index'])->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://processor.test/v1/documents/prepare'
        && $request->hasHeader('X-Internal-Token', 'processor-token'));
});

test('document processor client exposes python validation errors', function () {
    config()->set('services.document_processor.url', 'http://processor.test');
    config()->set('services.document_processor.token', 'processor-token');
    Http::fake([
        'http://processor.test/v1/documents/prepare' => Http::response([
            'detail' => 'PDF не содержит страниц.',
        ], 422),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'knowledge-pdf-');

    try {
        expect(fn () => app(DocumentProcessorClient::class)->prepare($path))
            ->toThrow(RuntimeException::class, 'PDF не содержит страниц.');
    } finally {
        @unlink($path);
    }
});
