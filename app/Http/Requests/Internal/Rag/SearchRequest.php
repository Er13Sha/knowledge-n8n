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
        ];
    }
}
