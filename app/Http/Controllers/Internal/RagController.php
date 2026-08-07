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

        return response()->json($indexer->index($document));
    }

    public function search(SearchRequest $request, KnowledgeSearchEngine $searchEngine): JsonResponse
    {
        return response()->json($searchEngine->search(
            $request->integer('user_id'),
            $request->string('question')->toString(),
            $request->filled('document_id') ? $request->integer('document_id') : null,
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
