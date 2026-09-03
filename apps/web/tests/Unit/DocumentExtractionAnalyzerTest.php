<?php

use App\Services\Extraction\DocumentExtractionAnalyzer;

test('analyzer extracts universal fields and resume details', function () {
    $result = app(DocumentExtractionAnalyzer::class)->analyze([
        'format' => 'pdf',
        'text' => "ФИО: Иван Иванов\nИИН: 123456789012\nТелефон: +7 777 123-45-67\nРезюме\nОпыт работы: 5 лет\nEmail: ivan@example.com\nНавыки: PHP Laravel SQL",
        'pages' => 1,
        'tables' => [],
        'metadata' => [],
        'stats' => ['ocr_used' => false],
    ]);

    expect($result['document_type'])->toBe('resume')
        ->and($result['emails'])->toContain('ivan@example.com')
        ->and($result['fields'])->toContain(['label' => 'ФИО', 'value' => 'Иван Иванов'])
        ->and($result['fields'])->toContain(['label' => 'ИИН', 'value' => '123456789012'])
        ->and($result['fields'])->toContain(['label' => 'Телефон', 'value' => '+7 777 123-45-67'])
        ->and($result['resume']['years_of_experience'])->toBe(5)
        ->and($result['resume']['skills'])->toContain('Laravel');
});
