<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreKnowledgeDocumentRequest extends FormRequest
{
    public const int MaxPdfKilobytes = 51_200;

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
