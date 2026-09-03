<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreKnowledgeSearchFeedbackRequest;
use App\Models\KnowledgeSearchInteraction;
use Illuminate\Http\JsonResponse;

class KnowledgeSearchFeedbackController extends Controller
{
    public function __invoke(
        StoreKnowledgeSearchFeedbackRequest $request,
        KnowledgeSearchInteraction $interaction,
    ): JsonResponse {
        abort_unless($interaction->user_id === $request->user()->id, 404);

        $interaction->update([
            'feedback_rating' => $request->string('rating')->toString(),
            'feedback_comment' => $request->string('comment')->toString() ?: null,
            'feedback_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'rating' => $interaction->feedback_rating,
                'message' => 'Спасибо за оценку ответа.',
            ],
        ]);
    }
}
