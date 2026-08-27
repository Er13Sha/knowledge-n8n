<?php

namespace App\Http\Requests\Knowledge;

use App\Models\Knowledge;
use App\Services\Access\AccessManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreKnowledgeDocumentRequest extends FormRequest
{
    public const int MaxPdfKilobytes = 51_200;

    public function authorize(): bool
    {
        return $this->user() !== null
            && app(AccessManager::class)->allows($this->user(), AccessManager::KnowledgeCreate);
    }

    /**
     * @return array<string, list<string>>
     */
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
            'document' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.self::MaxPdfKilobytes],
        ];
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile $document */
        $document = $this->validated('document');

        return $document;
    }
}
