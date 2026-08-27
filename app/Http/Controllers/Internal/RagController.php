<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Rag\DeleteDocumentRequest;
use App\Http\Requests\Internal\Rag\IndexDocumentRequest;
use App\Http\Requests\Internal\Rag\SearchRequest;
use App\Models\KnowledgeDocument;
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

    public function search(SearchRequest $request, KnowledgeSearchEngine $searchEngine): JsonResponse
    {
        return response()->json($searchEngine->search(
            $request->integer('user_id'),
            $request->string('question')->toString(),
            $request->filled('document_id') ? $request->integer('document_id') : null,
            $request->string('mode', 'rag')->toString(),
            array_map('intval', $request->input('document_ids', [])),
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
