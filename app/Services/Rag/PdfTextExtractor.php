<?php

namespace App\Services\Rag;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class PdfTextExtractor
{
    public function __construct(private TextProcessor $textProcessor) {}

    /** @return list<string> */
    public function extractPages(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('PDF-файл не найден.');
        }

        $pageCount = $this->pageCount($path);
        $pages = [];

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $this->extractPage($path, $pageNumber);
        }

        return $pages;
    }

    private function pageCount(string $path): int
    {
        $result = Process::timeout($this->pageTimeout())->run(['pdfinfo', $path]);

        if ($result->failed() || preg_match('/^Pages:\s+(\d+)$/mi', $result->output(), $matches) !== 1) {
            throw new RuntimeException('Не удалось прочитать PDF: '.$this->processError($result));
        }

        return (int) $matches[1];
    }

    private function extractPage(string $path, int $pageNumber): string
    {
        $result = Process::timeout($this->pageTimeout())->run([
            'pdftotext',
            '-f',
            (string) $pageNumber,
            '-l',
            (string) $pageNumber,
            '-layout',
            '-nopgbrk',
            $path,
            '-',
        ]);

        if ($result->successful()) {
            $text = $this->textProcessor->normalize($result->output());

            if ($text !== '') {
                return $text;
            }
        }

        return $this->ocrPage($path, $pageNumber);
    }

    private function ocrPage(string $path, int $pageNumber): string
    {
        $temporaryDirectory = sys_get_temp_dir().'/knowledge-ocr-'.Str::uuid();

        if (! File::makeDirectory($temporaryDirectory, 0700, true)) {
            throw new RuntimeException('Не удалось создать временный каталог для OCR.');
        }

        try {
            $imagePrefix = $temporaryDirectory.'/page';
            $renderResult = Process::timeout($this->pageTimeout())->run([
                'pdftoppm',
                '-f',
                (string) $pageNumber,
                '-l',
                (string) $pageNumber,
                '-r',
                (string) config('services.rag.ocr_dpi', 200),
                '-png',
                '-singlefile',
                $path,
                $imagePrefix,
            ]);

            if ($renderResult->failed()) {
                throw new RuntimeException('Не удалось отрендерить страницу: '.$this->processError($renderResult));
            }

            $ocrResult = Process::timeout($this->pageTimeout())->run([
                'tesseract',
                $imagePrefix.'.png',
                'stdout',
                '-l',
                (string) config('services.rag.ocr_languages', 'rus+eng'),
            ]);

            if ($ocrResult->failed()) {
                throw new RuntimeException('Не удалось распознать страницу: '.$this->processError($ocrResult));
            }

            return $this->textProcessor->normalize($ocrResult->output());
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function pageTimeout(): int
    {
        return (int) config('services.rag.ocr_page_timeout', 120);
    }

    private function processError(ProcessResult $result): string
    {
        return trim($result->errorOutput()) ?: 'неизвестная ошибка';
    }
}
