<?php

namespace App\Http\Controllers\Extraction;

use App\Enums\DocumentExtractionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Extraction\StoreDocumentExtractionRequest;
use App\Http\Resources\DocumentExtractionResource;
use App\Jobs\ProcessDocumentExtraction;
use App\Models\DocumentExtraction;
use App\Services\Access\AccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentExtractionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeExtraction($request);

        $extractions = DocumentExtraction::query()
            ->visibleTo($request->user())
            ->with('user')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => DocumentExtractionResource::collection($extractions)->resolve()]);
    }

    public function store(StoreDocumentExtractionRequest $request): JsonResponse
    {
        $this->authorizeExtraction($request);

        $file = $request->document();
        $path = $file->store('document-extractions');
        $extraction = DocumentExtraction::query()->create([
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'status' => DocumentExtractionStatus::Pending,
            'progress' => 0,
        ]);

        ProcessDocumentExtraction::dispatch($extraction->id);

        return response()->json([
            'data' => DocumentExtractionResource::make($extraction)->resolve(),
            'message' => 'Файл загружен и отправлен на извлечение данных.',
        ], 202);
    }

    public function show(Request $request, DocumentExtraction $extraction): JsonResponse
    {
        $this->authorizeExtraction($request);

        $extraction = $this->visible($request, $extraction)->load('user');
        return response()->json(['data' => DocumentExtractionResource::make($extraction)->resolve()]);
    }

    public function retry(Request $request, DocumentExtraction $extraction): JsonResponse
    {
        $this->authorizeExtraction($request);

        $extraction = $this->visible($request, $extraction);
        abort_unless($extraction->status === DocumentExtractionStatus::Failed, 422, 'Повторить можно только неудачную обработку.');
        $extraction->forceFill(['status' => DocumentExtractionStatus::Pending, 'progress' => 0, 'error_message' => null, 'result' => null, 'completed_at' => null])->save();
        ProcessDocumentExtraction::dispatch($extraction->id);
        return response()->json(['data' => DocumentExtractionResource::make($extraction)->resolve()]);
    }

    public function destroy(Request $request, DocumentExtraction $extraction): JsonResponse
    {
        $this->authorizeExtraction($request);

        $extraction = $this->visible($request, $extraction);
        Storage::disk($extraction->disk)->delete($extraction->path);
        $extraction->delete();
        return response()->json(status: 204);
    }

    public function download(Request $request, DocumentExtraction $extraction): Response
    {
        $this->authorizeExtraction($request);

        $extraction = $this->visible($request, $extraction);
        abort_unless(Storage::disk($extraction->disk)->exists($extraction->path), 404);
        return Storage::disk($extraction->disk)->download($extraction->path, $extraction->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function downloadJson(Request $request, DocumentExtraction $extraction): StreamedResponse
    {
        $this->authorizeExtraction($request);

        $extraction = $this->visible($request, $extraction);
        return response()->streamDownload(static function () use ($extraction): void {
            echo json_encode($extraction->result ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }, pathinfo($extraction->original_name, PATHINFO_FILENAME).'.json', ['Content-Type' => 'application/json; charset=utf-8']);
    }

    private function authorizeExtraction(Request $request): void
    {
        abort_unless(
            app(AccessManager::class)->allows($request->user(), AccessManager::ExtractionUse),
            403,
        );
    }

    private function visible(Request $request, DocumentExtraction $extraction): DocumentExtraction
    {
        return DocumentExtraction::query()->visibleTo($request->user())->whereKey($extraction->id)->firstOrFail();
    }
}
