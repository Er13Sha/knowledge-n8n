<?php

namespace App\Services\Rag;

use InvalidArgumentException;

class TextProcessor
{
    /** @var list<string> */
    private const SEARCH_STOP_WORDS = [
        'без', 'был', 'была', 'были', 'быть', 'вам', 'вас', 'весь', 'для', 'его', 'или', 'как',
        'когда', 'который', 'мне', 'над', 'она', 'они', 'оно', 'при', 'про', 'так', 'такое', 'такой',
        'там', 'чем', 'что', 'это', 'этот',
    ];

    public function normalize(string $value): string
    {
        $lines = preg_split('/\R/u', $value) ?: [];
        $normalizedLines = [];

        foreach ($lines as $line) {
            $normalizedLine = trim((string) preg_replace('/[ \t]+/u', ' ', $line));

            if ($normalizedLine !== '') {
                $normalizedLines[] = $normalizedLine;
            }
        }

        return trim(implode("\n", $normalizedLines));
    }

    /** @return list<string> */
    public function split(string $value, int $chunkSize, int $overlap): array
    {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException('Chunk size must be positive.');
        }

        if ($overlap < 0 || $overlap >= $chunkSize) {
            throw new InvalidArgumentException('Overlap must be between zero and chunk size.');
        }

        $text = $this->normalize($value);

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $start = 0;
        $textLength = mb_strlen($text);

        while ($start < $textLength) {
            $end = min($start + $chunkSize, $textLength);

            if ($end < $textLength) {
                $candidate = mb_substr($text, $start, $end - $start);
                $newLineBoundary = mb_strrpos($candidate, "\n");
                $spaceBoundary = mb_strrpos($candidate, ' ');
                $boundary = max($newLineBoundary === false ? -1 : $newLineBoundary, $spaceBoundary === false ? -1 : $spaceBoundary);

                if ($boundary > intdiv($chunkSize, 2)) {
                    $end = $start + $boundary;
                }
            }

            $chunk = trim(mb_substr($text, $start, $end - $start));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            if ($end >= $textLength) {
                break;
            }

            $start = max($end - $overlap, $start + 1);
        }

        return $chunks;
    }

    /** @return list<string> */
    public function searchTerms(string $query, int $limit = 12): array
    {
        preg_match_all('/[\p{L}\p{N}_-]+/u', mb_strtolower($query), $matches);
        $terms = [];

        foreach ($matches[0] as $term) {
            $normalizedTerm = trim($term, '-_');

            if (mb_strlen($normalizedTerm) < 3 || in_array($normalizedTerm, self::SEARCH_STOP_WORDS, true) || in_array($normalizedTerm, $terms, true)) {
                continue;
            }

            $terms[] = $normalizedTerm;

            if (count($terms) >= $limit) {
                break;
            }
        }

        return $terms;
    }

    public function isSingleWordQuery(string $query): bool
    {
        $normalizedQuery = trim(trim($query), "\"'«»");

        return preg_match('/^[\p{L}\p{N}_-]+$/u', $normalizedQuery) === 1;
    }

    /**
     * @param  list<array{payload?: array<string, mixed>}>  $points
     * @return list<array{document_id: int, document_name: string, page: int, chunk_index: int, excerpt: string, matched_terms: list<string>, phrase_matched: bool, match_type: string, retrieval: string}>
     */
    public function lexicalMatches(array $points, string $query, int $limit = 50): array
    {
        $terms = $this->searchTerms($query);
        $phrase = $this->queryPhrase($query);

        if ($terms === [] && mb_strlen($phrase) >= 3) {
            $terms = [$phrase];
        }

        $rankedMatches = [];
        $seen = [];

        foreach ($points as $point) {
            $payload = $point['payload'] ?? [];
            $text = (string) ($payload['text'] ?? '');
            $foldedText = mb_strtolower(str_replace("\n", ' ', $this->normalize($text)));
            $termOccurrences = [];
            $matchedTerms = [];

            foreach ($terms as $term) {
                $termOccurrences[$term] = $this->termPositions($foldedText, $term);

                if ($termOccurrences[$term] !== []) {
                    $matchedTerms[] = $term;
                }
            }

            if ($matchedTerms === []) {
                continue;
            }

            $phrasePositions = mb_strlen($phrase) >= 3 ? $this->termPositions($foldedText, $phrase) : [];
            $phraseMatched = $phrasePositions !== [];
            $positions = $phraseMatched
                ? [$phrasePositions[0]]
                : array_map(fn (string $term): int => $termOccurrences[$term][0], $matchedTerms);
            $excerpt = $this->excerpt($text, $positions);
            $documentId = (int) ($payload['document_id'] ?? 0);
            $page = (int) ($payload['page'] ?? 0);
            $deduplicationKey = implode(':', [$documentId, $page, mb_strtolower($excerpt)]);

            if (isset($seen[$deduplicationKey])) {
                continue;
            }

            $seen[$deduplicationKey] = true;
            $occurrenceCount = array_sum(array_map(fn (string $term): int => count($termOccurrences[$term]), $matchedTerms));
            $rank = ($phraseMatched ? 1000 : 0) + (count($matchedTerms) * 100) + $occurrenceCount;
            $rankedMatches[] = [
                'rank' => $rank,
                'match' => [
                'document_id' => $documentId,
                'document_name' => (string) ($payload['original_name'] ?? 'Документ'),
                'page' => $page,
                'chunk_index' => (int) ($payload['chunk_index'] ?? 0),
                'excerpt' => $excerpt,
                'matched_terms' => $matchedTerms,
                'phrase_matched' => $phraseMatched,
                'match_type' => 'exact',
                'retrieval' => 'lexical',
            ],
            ];
        }

        usort($rankedMatches, function (array $left, array $right): int {
            return $right['rank'] <=> $left['rank']
                ?: strcasecmp($left['match']['document_name'], $right['match']['document_name'])
                ?: $left['match']['page'] <=> $right['match']['page'];
        });

        return array_map(
            fn (array $rankedMatch): array => $rankedMatch['match'],
            array_slice($rankedMatches, 0, $limit),
        );
    }

    private function queryPhrase(string $query): string
    {
        preg_match_all('/[\p{L}\p{N}_-]+/u', mb_strtolower($query), $matches);

        return trim(implode(' ', $matches[0]));
    }

    /** @param non-empty-list<int> $positions */
    private function excerpt(string $text, array $positions, int $length = 520): string
    {
        $normalizedText = str_replace("\n", ' ', $this->normalize($text));
        $startPosition = min($positions);
        $start = max(0, $startPosition - intdiv($length, 3));
        $end = min(mb_strlen($normalizedText), $start + $length);

        if ($start > 0) {
            $boundary = mb_strpos($normalizedText, ' ', $start);

            if ($boundary !== false && $boundary < $startPosition) {
                $start = $boundary + 1;
            }
        }

        if ($end < mb_strlen($normalizedText)) {
            $candidate = mb_substr($normalizedText, $start, $end - $start);
            $boundary = mb_strrpos($candidate, ' ');

            if ($boundary !== false && ($start + $boundary) > $startPosition) {
                $end = $start + $boundary;
            }
        }

        $prefix = $start > 0 ? '…' : '';
        $suffix = $end < mb_strlen($normalizedText) ? '…' : '';

        return $prefix.trim(mb_substr($normalizedText, $start, $end - $start)).$suffix;
    }

    /** @return list<int> */
    private function termPositions(string $text, string $term): array
    {
        $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($term, '/').'(?![\p{L}\p{N}_])/iu';
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        return array_map(
            fn (array $match): int => mb_strlen(substr($text, 0, $match[1])),
            $matches[0] ?? [],
        );
    }
}
