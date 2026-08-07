<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\SearchKnowledgeDocumentsRequest;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class KnowledgeSearchController extends Controller
{
    public function __invoke(SearchKnowledgeDocumentsRequest $request, KnowledgeSearchService $searchService): JsonResponse
    {
        $document = $request->knowledgeDocument();

        if ($document !== null && $document->status !== KnowledgeDocumentStatus::Indexed) {
            throw ValidationException::withMessages([
                'document_id' => 'Документ ещё не проиндексирован.',
            ]);
        }

        if ($document === null && ! KnowledgeDocument::query()
            ->whereBelongsTo($request->user())
            ->where('status', KnowledgeDocumentStatus::Indexed)
            ->exists()) {
            throw ValidationException::withMessages([
                'document_id' => 'В базе знаний нет проиндексированных документов.',
            ]);
        }

        try {
            $result = $searchService->ask(
                $request->user()->id,
                $request->question(),
                $document?->id,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Не удалось выполнить поиск: '.$exception->getMessage(),
            ], 502);
        }

        return response()->json(['data' => $result]);
    }
}
