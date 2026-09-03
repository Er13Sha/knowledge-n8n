<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KnowledgeSearchInteractionService;
use Illuminate\Http\JsonResponse;

class KnowledgeSearchQualityController extends Controller
{
    public function __invoke(KnowledgeSearchInteractionService $interactionService): JsonResponse
    {
        return response()->json([
            'data' => $interactionService->qualitySummary(),
        ]);
    }
}
