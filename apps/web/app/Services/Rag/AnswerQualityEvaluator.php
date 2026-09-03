<?php

namespace App\Services\Rag;

class AnswerQualityEvaluator
{
    /**
     * @param  list<array<string, mixed>>  $sources
     * @return array{answer_status: string, confidence: string, citations_valid: bool, cited_source_numbers: list<int>}
     */
    public function evaluate(string $answer, array $sources): array
    {
        $citedSourceNumbers = $this->citedSourceNumbers($answer);
        $sourceCount = count($sources);
        $invalidCitation = collect($citedSourceNumbers)
            ->contains(fn (int $number): bool => $number < 1 || $number > $sourceCount);

        if ($sourceCount === 0) {
            return [
                'answer_status' => 'insufficient_evidence',
                'confidence' => 'low',
                'citations_valid' => false,
                'cited_source_numbers' => [],
            ];
        }

        $citationsValid = $citedSourceNumbers !== [] && ! $invalidCitation;

        if (! $citationsValid) {
            return [
                'answer_status' => 'citation_error',
                'confidence' => 'low',
                'citations_valid' => false,
                'cited_source_numbers' => $citedSourceNumbers,
            ];
        }

        return [
            'answer_status' => 'grounded',
            'confidence' => $this->confidence($sources, $citedSourceNumbers),
            'citations_valid' => true,
            'cited_source_numbers' => $citedSourceNumbers,
        ];
    }

    /** @return list<int> */
    private function citedSourceNumbers(string $answer): array
    {
        preg_match_all('/\[(\d+)\]/u', $answer, $matches);

        return array_values(array_unique(array_map(
            'intval',
            $matches[1] ?? [],
        )));
    }

    /** @param list<array<string, mixed>> $sources */
    private function confidence(array $sources, array $citedSourceNumbers): string
    {
        $highScore = (float) config('services.rag.confidence_high_score', 0.75);
        $topScore = (float) (collect($sources)
            ->pluck('score')
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): float => (float) $score)
            ->max() ?? 0.0);

        if (count($citedSourceNumbers) >= 2 && $topScore >= $highScore) {
            return 'high';
        }

        return 'medium';
    }
}
