<?php

namespace App\Services;

use App\Models\KnowledgeSearchInteraction;
use App\Models\User;
use Illuminate\Support\Arr;

class KnowledgeSearchInteractionService
{
    /** @param array<string, mixed> $result */
    public function record(User $user, string $question, ?int $scopeDocumentId, array $result): KnowledgeSearchInteraction
    {
        $quality = is_array($result['quality'] ?? null) ? $result['quality'] : [
            'answer_status' => ($result['sources'] ?? []) === [] ? 'insufficient_evidence' : 'grounded',
            'confidence' => ($result['sources'] ?? []) === [] ? 'low' : 'medium',
            'citations_valid' => true,
        ];

        $sourceReferences = collect(is_array($result['sources'] ?? null) ? $result['sources'] : [])
            ->map(fn (mixed $source): array => Arr::only(is_array($source) ? $source : [], [
                'number', 'document_id', 'page', 'score',
            ]))
            ->values()
            ->all();

        return KnowledgeSearchInteraction::query()->create([
            'user_id' => $user->id,
            'scope_document_id' => $scopeDocumentId,
            'mode' => (string) ($result['mode'] ?? 'rag'),
            'question' => $question,
            'answer' => (string) ($result['answer'] ?? ''),
            'answer_status' => (string) ($quality['answer_status'] ?? 'insufficient_evidence'),
            'confidence' => (string) ($quality['confidence'] ?? 'low'),
            'citations_valid' => (bool) ($quality['citations_valid'] ?? false),
            'source_references' => $sourceReferences,
            'expires_at' => now()->addDays((int) config('services.rag.interaction_retention_days', 90)),
        ]);
    }

    /** @return array<string, mixed> */
    public function qualitySummary(): array
    {
        $active = KnowledgeSearchInteraction::query()->where('expires_at', '>', now());
        $total = (clone $active)->count();
        $feedbackCount = (clone $active)->whereNotNull('feedback_rating')->count();
        $positiveCount = (clone $active)->where('feedback_rating', 'positive')->count();

        $popularQuestions = (clone $active)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('question, COUNT(*) as total')
            ->groupBy('question')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (KnowledgeSearchInteraction $interaction): array => [
                'question' => $interaction->question,
                'total' => (int) $interaction->total,
            ])
            ->values()
            ->all();

        return [
            'total_interactions' => $total,
            'feedback_count' => $feedbackCount,
            'positive_feedback' => $positiveCount,
            'negative_feedback' => (clone $active)->where('feedback_rating', 'negative')->count(),
            'positive_rate' => $feedbackCount === 0 ? null : round($positiveCount / $feedbackCount * 100, 1),
            'citation_errors' => (clone $active)->where('answer_status', 'citation_error')->count(),
            'low_confidence' => (clone $active)->where('confidence', 'low')->count(),
            'popular_questions' => $popularQuestions,
        ];
    }
}
