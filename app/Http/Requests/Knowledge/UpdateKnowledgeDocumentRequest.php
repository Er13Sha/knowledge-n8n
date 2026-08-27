<?php

namespace App\Http\Requests\Knowledge;

use App\Models\Knowledge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'department_id' => [
                'required',
                'string',
                Rule::in(array_column(Knowledge::DepartmentOptions, 'value')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'doc_type' => [
                'required',
                'string',
                Rule::in(array_column(Knowledge::DocumentTypeOptions, 'value')),
            ],
            'approved_at' => ['required', 'date'],
        ];
    }
}
