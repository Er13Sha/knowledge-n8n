<?php

namespace App\Http\Requests\Knowledge;

use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Http\FormRequest;

class SearchKnowledgeDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'max:2000'],
        ];
    }

    public function knowledgeDocument(): ?KnowledgeDocument
    {
        if (! $this->filled('document_id')) {
            return null;
        }

        return KnowledgeDocument::query()
            ->whereBelongsTo($this->user())
            ->whereKey($this->integer('document_id'))
            ->firstOrFail();
    }

    public function question(): string
    {
        return (string) $this->validated('question');
    }
}
