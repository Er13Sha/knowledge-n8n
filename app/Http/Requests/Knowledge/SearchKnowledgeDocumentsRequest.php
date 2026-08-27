<?php

namespace App\Http\Requests\Knowledge;

use App\Models\KnowledgeDocument;
use App\Services\Access\AccessManager;
use Illuminate\Foundation\Http\FormRequest;

class SearchKnowledgeDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(AccessManager::class)->allows($this->user(), AccessManager::KnowledgeRead);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'max:2000'],
            'mode' => ['sometimes', 'string', 'in:fulltext,rag'],
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ];
    }

    public function knowledgeDocument(): ?KnowledgeDocument
    {
        if (! $this->filled('document_id')) {
            return null;
        }

        return app(AccessManager::class)
            ->visibleDocuments($this->user())
            ->whereKey($this->integer('document_id'))
            ->firstOrFail();
    }

    public function question(): string
    {
        return (string) $this->validated('question');
    }

    public function mode(): string
    {
        return (string) ($this->validated('mode') ?? 'rag');
    }

    /** @return list<array{role: string, content: string}> */
    public function history(): array
    {
        /** @var list<array{role: string, content: string}> $history */
        $history = $this->validated('history') ?? [];

        return $history;
    }
}
