<?php

namespace App\Http\Requests\Knowledge;

use App\Services\Access\AccessManager;
use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeSearchFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(AccessManager::class)->allows($this->user(), AccessManager::KnowledgeRead);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'string', 'in:positive,negative'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
