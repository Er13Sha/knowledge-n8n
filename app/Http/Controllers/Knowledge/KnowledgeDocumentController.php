<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreKnowledgeDocumentRequest;
use App\Http\Resources\KnowledgeDocumentResource;
use App\Jobs\DeleteKnowledgeDocumentIndex;
use App\Jobs\IndexKnowledgeDocument;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = KnowledgeDocument::query()
            ->whereBelongsTo($request->user())
            ->latest()
            ->get();

        return response()->json([
            'data' => KnowledgeDocumentResource::collection($documents)->resolve(),
            'meta' => $this->knowledgeBaseMeta(),
        ]);
    }

    public function store(StoreKnowledgeDocumentRequest $request): JsonResponse
    {
        $uploadedFile = $request->document();
        $path = $uploadedFile->store('knowledge-documents');

        $document = KnowledgeDocument::query()->create([
            'user_id' => $request->user()->id,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $uploadedFile->getMimeType(),
            'size' => $uploadedFile->getSize() ?: 0,
            'status' => KnowledgeDocumentStatus::Pending,
        ]);

        IndexKnowledgeDocument::dispatch($document->id);

        return response()->json([
            'data' => KnowledgeDocumentResource::make($document)->resolve(),
            'message' => 'PDF загружен и отправлен на индексацию.',
        ], 201);
    }

    public function show(Request $request, KnowledgeDocument $knowledgeDocument): StreamedResponse
    {
        abort_unless($knowledgeDocument->user_id === $request->user()->id, 404);

        $disk = Storage::disk($knowledgeDocument->disk);

        abort_unless($disk->exists($knowledgeDocument->path), 404);

        return $disk->response(
            $knowledgeDocument->path,
            $knowledgeDocument->original_name,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function destroy(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        abort_unless($knowledgeDocument->user_id === $request->user()->id, 404);

        $documentId = $knowledgeDocument->id;
        $userId = $knowledgeDocument->user_id;

        Storage::disk($knowledgeDocument->disk)->delete($knowledgeDocument->path);
        $knowledgeDocument->delete();
        DeleteKnowledgeDocumentIndex::dispatch($documentId, $userId);

        return response()->json(status: 204);
    }

    public function retryIndexing(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        abort_unless($knowledgeDocument->user_id === $request->user()->id, 404);

        $knowledgeDocument->forceFill([
            'status' => KnowledgeDocumentStatus::Pending,
            'error_message' => null,
            'indexed_at' => null,
        ])->save();

        IndexKnowledgeDocument::dispatch($knowledgeDocument->id);

        return response()->json([
            'data' => KnowledgeDocumentResource::make($knowledgeDocument)->resolve(),
            'message' => 'Документ повторно отправлен на индексацию.',
        ]);
    }

    /**
     * @return array{upload: array{max_pdf_mb: int}, services: array{n8n_index_configured: bool, n8n_search_configured: bool, ollama_url: mixed, ollama_model: mixed}}
     */
    private function knowledgeBaseMeta(): array
    {
        return [
            'upload' => [
                'max_pdf_mb' => intdiv(StoreKnowledgeDocumentRequest::MaxPdfKilobytes, 1024),
            ],
            'services' => [
                'n8n_index_configured' => filled(config('services.n8n.index_webhook_url')),
                'n8n_search_configured' => filled(config('services.n8n.search_webhook_url')),
                'ollama_url' => config('services.ollama.url'),
                'ollama_model' => config('services.ollama.model'),
            ],
        ];
    }
}
