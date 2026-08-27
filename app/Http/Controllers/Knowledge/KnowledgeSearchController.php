<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\SearchKnowledgeDocumentsRequest;
use App\Services\Access\AccessManager;
use App\Services\KnowledgeSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class KnowledgeSearchController extends Controller
{
    public function __invoke(SearchKnowledgeDocumentsRequest $request, KnowledgeSearchService $searchService): JsonResponse
    {
        $document = $request->knowledgeDocument();
        $visibleIndexedDocuments = app(AccessManager::class)
            ->visibleDocuments($request->user())
            ->where('status', KnowledgeDocumentStatus::Indexed);

        if ($document !== null && $document->status !== KnowledgeDocumentStatus::Indexed) {
            throw ValidationException::withMessages([
                'document_id' => 'Документ ещё не проиндексирован.',
            ]);
        }

        if ($document === null && ! $visibleIndexedDocuments->exists()) {
            throw ValidationException::withMessages([
                'document_id' => 'В базе знаний нет проиндексированных документов.',
            ]);
        }

        try {
            $result = $searchService->ask(
                $request->user()->id,
                $request->question(),
                $document?->id,
                $request->mode(),
                $document !== null
                    ? [$document->id]
                    : $visibleIndexedDocuments->pluck('id')->map(fn (int $id): int => $id)->all(),
                $request->history(),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Не удалось выполнить поиск: '.$exception->getMessage(),
            ], 502);
        }

        return response()->json(['data' => $result]);
    }
}
