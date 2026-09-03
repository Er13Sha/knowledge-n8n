<?php

namespace Database\Seeders;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\Department;
use App\Models\Knowledge;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KnowledgeDocumentSeeder extends Seeder
{
    private const int DocumentCount = 1000;

    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => env('SEED_KNOWLEDGE_USER_EMAIL', env('ADMIN_EMAIL', 'admin@example.com'))],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => env('ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
            ],
        );

        $pdf = $this->pdfContent();
        $departments = Department::options();
        $documentTypes = Knowledge::DocumentTypeOptions;

        DB::transaction(function () use ($user, $pdf, $departments, $documentTypes): void {
            for ($index = 1; $index <= self::DocumentCount; $index++) {
                $department = $departments[($index - 1) % count($departments)];
                $documentType = $documentTypes[($index - 1) % count($documentTypes)];
                $title = sprintf('Тестовый документ №%04d', $index);
                $path = sprintf('knowledge-documents/test-seed/%04d.pdf', $index);
                $createdAt = now()->subDays($index % 365)->subMinutes($index);

                Storage::disk('local')->put($path, $pdf);

                $knowledge = Knowledge::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'title' => $title,
                    ],
                    [
                        'department_id' => $department['value'],
                        'doc_type' => $documentType['value'],
                        'status' => KnowledgeDocumentStatus::Indexed->value,
                        'approved_at' => $createdAt->copy()->subDay(),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ],
                );

                KnowledgeDocument::query()->updateOrCreate(
                    ['knowledge_id' => $knowledge->id],
                    [
                        'user_id' => $user->id,
                        'original_name' => sprintf('test-document-%04d.pdf', $index),
                        'disk' => 'local',
                        'path' => $path,
                        'mime_type' => 'application/pdf',
                        'size' => strlen($pdf),
                        'status' => KnowledgeDocumentStatus::Indexed,
                        'indexed_at' => $createdAt,
                        'error_message' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ],
                );
            }
        });
    }

    private function pdfContent(): string
    {
        $stream = "BT /F1 18 Tf 72 720 Td (Test Knowledge Document) Tj ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $objectNumber => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($objectNumber + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf
            ."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n"
            .'startxref'."\n"
            .$xrefOffset."\n%%EOF\n";
    }
}
