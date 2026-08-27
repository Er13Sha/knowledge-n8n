<?php

namespace App\Http\Resources;

use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KnowledgeDocument
 */
class KnowledgeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $doc = $this->resource;
        $knowledge = $doc->knowledge;

        return [
            'id' => $doc->id,
            'original_name' => $doc->original_name,
            'title' => $knowledge?->title,
            'department_id' => $knowledge?->department_id,
            'department_label' => $knowledge?->departmentLabel(),
            'doc_type' => $knowledge?->doc_type,
            'doc_type_label' => $knowledge?->documentTypeLabel(),
            'approved_at' => $knowledge?->approved_at?->toDateString(),
            'size' => $doc->size,
            'human_size' => $this->humanSize($doc->size),
            'status' => $doc->status->value,
            'status_label' => $doc->status->label(),
            'index_progress' => $doc->index_progress,
            'is_searchable' => $doc->status->isSearchable(),
            'indexed_at' => $doc->indexed_at?->toISOString(),
            'error_message' => $doc->error_message,
            'created_at' => $doc->created_at?->toISOString(),
            'user_name' => $doc->user?->name,
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
