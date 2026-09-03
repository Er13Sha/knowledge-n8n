<?php

namespace App\Http\Requests\Extraction;

use App\Services\Access\AccessManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreDocumentExtractionRequest extends FormRequest
{
    public const int MaxKilobytes = 20_480;

    public function authorize(): bool
    {
        return $this->user() !== null
            && app(AccessManager::class)->allows($this->user(), AccessManager::ExtractionUse);
    }

    public function rules(): array
    {
        return ['document' => ['required', 'file', 'max:'.self::MaxKilobytes]];
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile $document */
        $document = $this->validated('document');

        return $document;
    }
}
