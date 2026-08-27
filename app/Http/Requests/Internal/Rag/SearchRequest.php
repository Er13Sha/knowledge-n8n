<?php

namespace App\Http\Requests\Internal\Rag;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'document_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['required', 'integer', 'min:1'],
            'question' => ['required', 'string', 'min:2', 'max:4000'],
            'mode' => ['sometimes', 'string', 'in:fulltext,rag'],
            'document_ids' => ['sometimes', 'array', 'max:5000'],
            'document_ids.*' => ['integer', 'min:1'],
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ];
    }
}
