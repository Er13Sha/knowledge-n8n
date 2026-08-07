<?php

namespace App\Services\Rag;

class KnowledgeSearchEngine
{
    public function __construct(
        private TextProcessor $textProcessor,
        private OllamaClient $ollamaClient,
        private QdrantVectorStore $vectorStore,
    ) {}

    /** @return array{answer: string, sources: list<mixed>, matches: list<array<string, mixed>>} */
    public function search(int $userId, string $question, ?int $documentId = null): array
    {
        if ($this->textProcessor->isSingleWordQuery($question)) {
            $matches = $this->textProcessor->lexicalMatches(
                $this->vectorStore->scroll($userId, $documentId),
                $question,
                (int) config('services.rag.lexical_result_limit', 50),
            );

            return [
                'answer' => 'Найдено точных совпадений: '.count($matches).'.',
                'sources' => [],
                'matches' => $matches,
            ];
        }

        $vector = $this->ollamaClient->embed([$question])[0];
        $matches = $this->semanticMatches($this->vectorStore->semanticSearch($vector, $userId, $documentId));

        return [
            'answer' => 'Найдено смысловых фрагментов: '.count($matches).'.',
            'sources' => [],
            'matches' => $matches,
        ];
    }

    /**
     * @param  list<array{score?: float|int, payload?: array<string, mixed>}>  $points
     * @return list<array<string, mixed>>
     */
    private function semanticMatches(array $points): array
    {
        $matches = [];

        foreach ($points as $point) {
            $payload = $point['payload'] ?? [];
            $text = str_replace("\n", ' ', $this->textProcessor->normalize((string) ($payload['text'] ?? '')));
            $excerpt = mb_strlen($text) > 700 ? rtrim(mb_substr($text, 0, 700)).'…' : $text;
            $matches[] = [
                'document_id' => (int) ($payload['document_id'] ?? 0),
                'document_name' => (string) ($payload['original_name'] ?? 'Документ'),
                'page' => (int) ($payload['page'] ?? 0),
                'excerpt' => $excerpt,
                'matched_terms' => [],
                'phrase_matched' => false,
                'match_type' => 'semantic',
                'score' => round((float) ($point['score'] ?? 0), 4),
            ];
        }

        return $matches;
    }
}
