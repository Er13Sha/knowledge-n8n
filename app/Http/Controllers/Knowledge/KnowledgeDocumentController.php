<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreKnowledgeDocumentRequest;
use App\Http\Requests\Knowledge\UpdateKnowledgeDocumentRequest;
use App\Http\Resources\KnowledgeDocumentResource;
use App\Jobs\DeleteKnowledgeDocumentIndex;
use App\Jobs\IndexKnowledgeDocument;
use App\Models\Knowledge;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Access\AccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', KnowledgeDocument::class);

        $documents = app(AccessManager::class)
            ->visibleDocuments($request->user())
            ->with(['knowledge', 'user'])
            ->latest()
            ->get();

        return response()->json([
            'data' => KnowledgeDocumentResource::collection($documents)->resolve(),
            'meta' => $this->knowledgeBaseMeta($request->user()),
        ]);
    }

    public function store(StoreKnowledgeDocumentRequest $request): JsonResponse
    {
        $uploadedFile = $request->document();
        $path = $uploadedFile->store('knowledge-documents');
        $validated = $request->validated();

        $document = DB::transaction(function () use ($request, $uploadedFile, $path, $validated): KnowledgeDocument {
            $knowledge = Knowledge::query()->create([
                'user_id' => $request->user()->id,
                'department_id' => $validated['department_id'],
                'title' => $validated['title'],
                'doc_type' => $validated['doc_type'],
                'status' => KnowledgeDocumentStatus::Pending->value,
                'approved_at' => $validated['approved_at'],
            ]);

            return KnowledgeDocument::query()->create([
                'user_id' => $request->user()->id,
                'knowledge_id' => $knowledge->id,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize() ?: 0,
                'status' => KnowledgeDocumentStatus::Pending,
            ]);
        });

        IndexKnowledgeDocument::dispatch($document->id);

        return response()->json([
            'data' => KnowledgeDocumentResource::make($document)->resolve(),
            'message' => 'PDF загружен и отправлен на индексацию.',
        ], 201);
    }

    public function show(Request $request, KnowledgeDocument $knowledgeDocument): StreamedResponse
    {
        Gate::authorize('viewAny', KnowledgeDocument::class);
        abort_unless(
            app(AccessManager::class)->canAccessDocument(
                $request->user(),
                $knowledgeDocument,
                AccessManager::KnowledgeRead,
            ),
            404,
        );

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
        Gate::authorize('delete', $knowledgeDocument);

        $documentId = $knowledgeDocument->id;
        $userId = $knowledgeDocument->user_id;

        Storage::disk($knowledgeDocument->disk)->delete($knowledgeDocument->path);
        $knowledge = $knowledgeDocument->knowledge;
        $knowledgeDocument->delete();
        $knowledge?->delete();
        DeleteKnowledgeDocumentIndex::dispatch($documentId, $userId);

        return response()->json(status: 204);
    }

    public function retryIndexing(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        Gate::authorize('update', $knowledgeDocument);

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

    public function update(UpdateKnowledgeDocumentRequest $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        Gate::authorize('update', $knowledgeDocument);

        $knowledge = $knowledgeDocument->knowledge;
        abort_unless($knowledge !== null, 404);

        $knowledge->update($request->validated());

        return response()->json([
            'data' => KnowledgeDocumentResource::make($knowledgeDocument->fresh(['knowledge', 'user']))->resolve(),
        ]);
    }

    /**
     * @return array{upload: array{max_pdf_mb: int}, form: array{departments: list<array{value: string, title: string}>, document_types: list<array{value: string, title: string}>}, services: array{n8n_index_configured: bool, n8n_search_configured: bool, ollama_url: mixed, ollama_model: mixed}}
     */
    private function knowledgeBaseMeta(User $user): array
    {
        $access = app(AccessManager::class);

        return [
            'upload' => [
                'max_pdf_mb' => intdiv(StoreKnowledgeDocumentRequest::MaxPdfKilobytes, 1024),
            ],
            'form' => [
                'departments' => Knowledge::DepartmentOptions,
                'document_types' => Knowledge::DocumentTypeOptions,
            ],
            'services' => [
                'n8n_index_configured' => filled(config('services.n8n.index_webhook_url')),
                'n8n_search_configured' => filled(config('services.n8n.search_webhook_url')),
                'ollama_url' => config('services.ollama.url'),
                'ollama_model' => config('services.ollama.model'),
            ],
            'permissions' => $access->permissionKeys($user),
            'is_super_admin' => $user->is_super_admin,
        ];
    }
}
