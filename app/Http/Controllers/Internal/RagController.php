<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Rag\DeleteDocumentRequest;
use App\Http\Requests\Internal\Rag\IndexDocumentRequest;
use App\Http\Requests\Internal\Rag\SearchRequest;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Access\AccessManager;
use App\Services\Rag\KnowledgeIndexer;
use App\Services\Rag\KnowledgeSearchEngine;
use App\Services\Rag\QdrantVectorStore;
use Illuminate\Http\JsonResponse;
use Throwable;

class RagController extends Controller
{
    public function index(IndexDocumentRequest $request, KnowledgeIndexer $indexer): JsonResponse
    {
        $document = KnowledgeDocument::query()
            ->whereKey($request->integer('document_id'))
            ->where('user_id', $request->integer('user_id'))
            ->where('original_name', $request->string('original_name')->toString())
            ->where('path', $request->string('path')->toString())
            ->firstOrFail();

        try {
            return response()->json($indexer->index($document));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'detail' => 'Индексация не выполнена: '.$exception->getMessage(),
            ], 502);
        }
    }

    public function search(
        SearchRequest $request,
        KnowledgeSearchEngine $searchEngine,
        AccessManager $accessManager,
    ): JsonResponse {
        $user = User::query()->findOrFail($request->integer('user_id'));
        abort_unless($accessManager->allows($user, AccessManager::KnowledgeRead), 403);

        $requestedDocumentIds = array_values(array_unique(array_map(
            'intval',
            $request->input('document_ids', []),
        )));
        $visibleDocuments = $accessManager->visibleDocuments($user);

        if ($request->filled('document_id')) {
            abort_unless(
                (clone $visibleDocuments)->whereKey($request->integer('document_id'))->exists(),
                404,
            );
        }

        $documentIds = $requestedDocumentIds === []
            ? []
            : (clone $visibleDocuments)->whereIn('id', $requestedDocumentIds)->pluck('id')->map(fn (int $id): int => $id)->all();

        if ($requestedDocumentIds !== [] && $documentIds === []) {
            $documentIds = [0];
        }

        return response()->json($searchEngine->search(
            $user->id,
            $request->string('question')->toString(),
            $request->filled('document_id') ? $request->integer('document_id') : null,
            $request->string('mode', 'rag')->toString(),
            $documentIds,
            $request->input('history', []),
        ));
    }

    public function destroy(DeleteDocumentRequest $request, QdrantVectorStore $vectorStore): JsonResponse
    {
        $vectorStore->deleteDocument(
            $request->integer('document_id'),
            $request->integer('user_id'),
        );

        return response()->json(['ok' => true]);
    }
}
