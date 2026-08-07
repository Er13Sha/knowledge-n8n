<?php

use App\Services\Rag\PdfTextExtractor;
use App\Services\Rag\TextProcessor;
use Illuminate\Support\Facades\Process;

test('pdf extractor keeps existing page text without ocr', function () {
    Process::fake([
        '*pdfinfo*' => Process::result(output: 'Pages: 1'),
        '*pdftotext*' => Process::result(output: '  Existing   text '),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'knowledge-pdf-');

    try {
        $pages = (new PdfTextExtractor(new TextProcessor))->extractPages($path);
    } finally {
        @unlink($path);
    }

    expect($pages)->toBe(['Existing text']);
    Process::assertNotRan(fn ($process): bool => str_contains(implode(' ', (array) $process->command), 'tesseract'));
});

test('pdf extractor uses ocr when a page has no text', function () {
    Process::fake([
        '*pdfinfo*' => Process::result(output: 'Pages: 1'),
        '*pdftotext*' => Process::result(),
        '*pdftoppm*' => Process::result(),
        '*tesseract*' => Process::result(output: '  Распознанный   текст '),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'knowledge-pdf-');

    try {
        $pages = (new PdfTextExtractor(new TextProcessor))->extractPages($path);
    } finally {
        @unlink($path);
    }

    expect($pages)->toBe(['Распознанный текст']);
    Process::assertRan(fn ($process): bool => str_contains(implode(' ', (array) $process->command), 'tesseract'));
});
