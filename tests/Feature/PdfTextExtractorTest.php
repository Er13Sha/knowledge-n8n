<?php

use App\Services\Rag\DocumentProcessorClient;
use App\Services\Rag\PdfTextExtractor;

test('pdf extractor delegates page extraction to the python document processor', function () {
    $documentProcessor = Mockery::mock(DocumentProcessorClient::class);
    $documentProcessor->shouldReceive('prepare')->once()->with('/tmp/document.pdf')->andReturn([
        'pages' => [
            ['page' => 1, 'text' => 'Existing text', 'ocr_used' => false],
            ['page' => 2, 'text' => 'Распознанный текст', 'ocr_used' => true],
        ],
        'chunks' => [],
    ]);

    $pages = (new PdfTextExtractor($documentProcessor))->extractPages('/tmp/document.pdf');

    expect($pages)->toBe(['Existing text', 'Распознанный текст']);
});
