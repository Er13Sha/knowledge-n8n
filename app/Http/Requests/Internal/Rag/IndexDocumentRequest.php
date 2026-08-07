<?php

namespace App\Http\Requests\Internal\Rag;

use Illuminate\Foundation\Http\FormRequest;

class IndexDocumentRequest extends FormRequest
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
            'document_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'integer', 'min:1'],
            'original_name' => ['required', 'string', 'min:1', 'max:255'],
            'path' => ['required', 'string', 'min:1', 'max:1024'],
        ];
    }
}
