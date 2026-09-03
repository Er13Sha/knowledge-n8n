<?php

namespace App\Services\Rag;

class KnowledgeSearchEngine
{
    public function __construct(
        private TextProcessor $textProcessor,
        private OllamaClient $ollamaClient,
        private QdrantVectorStore $vectorStore,
        private ?AnswerQualityEvaluator $qualityEvaluator = null,
    ) {}

    /**
     * @param  list<int>  $documentIds
     * @param  list<array{role: string, content: string}>  $history
     * @return array{mode: string, answer: string, sources: list<array<string, mixed>>, matches: list<array<string, mixed>>, quality: array<string, mixed>}
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
                'quality' => [
                    'answer_status' => $matches === [] ? 'insufficient_evidence' : 'grounded',
                    'confidence' => $matches === [] ? 'low' : 'medium',
                    'citations_valid' => true,
                    'cited_source_numbers' => [],
                ],
            ];
        }

        $vector = $this->ollamaClient->embed([$this->retrievalQuestion($question, $history)])[0];
        $semanticMatches = $this->semanticMatches(
            $this->vectorStore->semanticSearch($vector, $userId, $documentId, $documentIds),
        );
        $searchTerms = $this->textProcessor->searchTerms($question);
        $candidateQuery = $searchTerms === [] ? trim($question) : implode(' ', $searchTerms);
        $lexicalMatches = $this->textProcessor->lexicalMatches(
            $this->vectorStore->fullTextSearch($candidateQuery, $userId, $documentId, $documentIds),
            $question,
            (int) config('services.rag.lexical_result_limit', 50),
        );
        $matches = $this->hybridMatches($semanticMatches, $lexicalMatches);
        $sources = $this->sources($matches);
        $answer = $sources === []
            ? 'В доступных документах не найдено достаточно данных для ответа.'
            : $this->ollamaClient->answer($question, $sources, $history);
        $quality = ($this->qualityEvaluator ?? new AnswerQualityEvaluator())->evaluate($answer, $sources);

        if ($quality['answer_status'] === 'citation_error') {
            $answer = 'Не удалось сформировать подтверждённый ответ. Проверьте найденные источники.';
        }

        return [
            'mode' => 'rag',
            'answer' => $answer,
            'sources' => $sources,
            'matches' => $matches,
            'quality' => $quality,
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
                'chunk_index' => (int) ($payload['chunk_index'] ?? 0),
                'excerpt' => $excerpt,
                'matched_terms' => [],
                'phrase_matched' => false,
                'match_type' => 'semantic',
                'retrieval' => 'semantic',
                'score' => round((float) ($point['score'] ?? 0), 4),
            ];
        }

        return $matches;
    }

    /**
     * @param  list<array<string, mixed>>  $semanticMatches
     * @param  list<array<string, mixed>>  $lexicalMatches
     * @return list<array<string, mixed>>
     */
    private function hybridMatches(array $semanticMatches, array $lexicalMatches): array
    {
        $merged = [];

        foreach ($semanticMatches as $rank => $match) {
            $key = $this->matchKey($match);
            $merged[$key] = [
                'match' => $match,
                'semantic_rank' => $rank + 1,
                'lexical_rank' => null,
            ];
        }

        foreach ($lexicalMatches as $rank => $match) {
            $key = $this->matchKey($match);

            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'match' => $match,
                    'semantic_rank' => null,
                    'lexical_rank' => $rank + 1,
                ];

                continue;
            }

            $merged[$key]['match']['match_type'] = 'hybrid';
            $merged[$key]['match']['retrieval'] = 'hybrid';
            $merged[$key]['match']['matched_terms'] = array_values(array_unique(array_merge(
                $merged[$key]['match']['matched_terms'] ?? [],
                $match['matched_terms'] ?? [],
            )));
            $merged[$key]['match']['phrase_matched'] =
                ($merged[$key]['match']['phrase_matched'] ?? false) || ($match['phrase_matched'] ?? false);
            $merged[$key]['lexical_rank'] = $rank + 1;
        }

        $weightedSemantic = 0.6;
        $weightedLexical = 0.4;
        $rrfConstant = 60;

        foreach ($merged as &$entry) {
            $entry['fusion_score'] = ($entry['semantic_rank'] === null ? 0 : $weightedSemantic / ($rrfConstant + $entry['semantic_rank']))
                + ($entry['lexical_rank'] === null ? 0 : $weightedLexical / ($rrfConstant + $entry['lexical_rank']));
        }
        unset($entry);

        usort($merged, function (array $left, array $right): int {
            return $right['fusion_score'] <=> $left['fusion_score']
                ?: strcasecmp($left['match']['document_name'], $right['match']['document_name'])
                ?: $left['match']['page'] <=> $right['match']['page'];
        });

        return array_values(array_map(
            fn (array $entry): array => $entry['match'],
            array_slice($merged, 0, (int) config('services.rag.top_k', 6)),
        ));
    }

    /** @param array<string, mixed> $match */
    private function matchKey(array $match): string
    {
        return implode(':', [
            (int) ($match['document_id'] ?? 0),
            (int) ($match['page'] ?? 0),
            (int) ($match['chunk_index'] ?? 0),
        ]);
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
