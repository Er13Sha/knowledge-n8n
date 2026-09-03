<?php

namespace App\Http\Resources;

use App\Models\DocumentExtraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentExtraction */
class DocumentExtractionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $extraction = $this->resource;

        return [
            'id' => $extraction->id,
            'original_name' => $extraction->original_name,
            'mime_type' => $extraction->mime_type,
            'detected_format' => $extraction->detected_format,
            'size' => $extraction->size,
            'human_size' => $this->humanSize($extraction->size),
            'status' => $extraction->status->value,
            'status_label' => $extraction->status->label(),
            'progress' => $extraction->progress,
            'result' => $extraction->result,
            'error_message' => $extraction->error_message,
            'completed_at' => $extraction->completed_at?->toISOString(),
            'created_at' => $extraction->created_at?->toISOString(),
            'user_name' => $extraction->user?->name,
        ];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1_048_576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1_048_576, 1).' MB';
    }
}
