<?php

namespace App\Services\Rag;

class KnowledgeSearchEngine
{
    public function __construct(
        private TextProcessor $textProcessor,
        private OllamaClient $ollamaClient,
        private QdrantVectorStore $vectorStore,
    ) {}

    /**
     * @param  list<int>  $documentIds
     * @param  list<array{role: string, content: string}>  $history
     * @return array{mode: string, answer: string, sources: list<array<string, mixed>>, matches: list<array<string, mixed>>}
     */
    public function search(
        int $userId,
        string $question,
        ?int $documentId = null,
        string $mode = 'rag',
        array $documentIds = [],
        array $history = [],
    ): array {
        if ($mode === 'fulltext') {
            $searchTerms = $this->textProcessor->searchTerms($question);
            $candidateQuery = $searchTerms === [] ? trim($question) : implode(' ', $searchTerms);
            $matches = $this->textProcessor->lexicalMatches(
                $this->vectorStore->fullTextSearch($candidateQuery, $userId, $documentId, $documentIds),
                $question,
                (int) config('services.rag.lexical_result_limit', 50),
            );

            return [
                'mode' => 'fulltext',
                'answer' => 'Найдено точных совпадений: '.count($matches).'.',
                'sources' => $this->sources($matches),
                'matches' => $matches,
            ];
        }

        $vector = $this->ollamaClient->embed([$this->retrievalQuestion($question, $history)])[0];
        $matches = $this->semanticMatches(
            $this->vectorStore->semanticSearch($vector, $userId, $documentId, $documentIds),
        );
        $sources = $this->sources($matches);

        return [
            'mode' => 'rag',
            'answer' => $sources === []
                ? 'В доступных документах не найдено достаточно данных для ответа.'
                : $this->ollamaClient->answer($question, $sources, $history),
            'sources' => $sources,
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

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function sources(array $matches): array
    {
        return array_values(array_map(
            fn (array $match, int $index): array => [
                'number' => $index + 1,
                'document_id' => (int) ($match['document_id'] ?? 0),
                'document_name' => (string) ($match['document_name'] ?? 'Документ'),
                'page' => (int) ($match['page'] ?? 0),
                'excerpt' => (string) ($match['excerpt'] ?? ''),
                'score' => isset($match['score']) ? (float) $match['score'] : null,
            ],
            $matches,
            array_keys($matches),
        ));
    }

    /** @param list<array{role: string, content: string}> $history */
    private function retrievalQuestion(string $question, array $history): string
    {
        $previousQuestions = array_values(array_map(
            fn (array $message): string => trim($message['content']),
            array_filter(
                array_slice($history, -6),
                fn (array $message): bool => $message['role'] === 'user' && trim($message['content']) !== '',
            ),
        ));

        return implode("\n", [...array_slice($previousQuestions, -2), trim($question)]);
    }
}
