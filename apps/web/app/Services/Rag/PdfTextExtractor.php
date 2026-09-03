<?php

namespace App\Services\Rag;

class PdfTextExtractor
{
    public function __construct(private DocumentProcessorClient $documentProcessor) {}

    /** @return list<string> */
    public function extractPages(string $path): array
    {
        return array_values(array_map(
            fn (array $page): string => $page['text'],
            $this->documentProcessor->prepare($path)['pages'],
        ));
    }
}
