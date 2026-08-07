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
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'human_size' => $this->humanSize(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_searchable' => $this->status->isSearchable(),
            'indexed_at' => $this->indexed_at?->toISOString(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function humanSize(): string
    {
        if ($this->size < 1024) {
            return $this->size.' B';
        }

        if ($this->size < 1_048_576) {
            return round($this->size / 1024, 1).' KB';
        }

        return round($this->size / 1_048_576, 1).' MB';
    }
}
