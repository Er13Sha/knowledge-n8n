<?php

use App\Services\Rag\TextProcessor;

beforeEach(function () {
    $this->textProcessor = new TextProcessor;
});

test('normalizes repeated whitespace and empty lines', function () {
    expect($this->textProcessor->normalize("  First   line\n\n Second\tline "))
        ->toBe("First line\nSecond line");
});

test('splits text while preserving overlap', function () {
    $chunks = $this->textProcessor->split('one two three four five six', 14, 4);

    expect($chunks)
        ->toHaveCount(3)
        ->and($chunks[0])->toStartWith('one two')
        ->and($chunks[array_key_last($chunks)])->toEndWith('six');
});

test('rejects invalid overlap', function () {
    $this->textProcessor->split('content', 10, 10);
})->throws(InvalidArgumentException::class);

test('removes question words and duplicate search terms', function () {
    expect($this->textProcessor->searchTerms('Что такое естественный отбор и что он изменяет?'))
        ->toBe(['естественный', 'отбор', 'изменяет']);
});

test('distinguishes a single word from a question', function () {
    expect($this->textProcessor->isSingleWordQuery('ионы'))->toBeTrue()
        ->and($this->textProcessor->isSingleWordQuery('«ионы»'))->toBeTrue()
        ->and($this->textProcessor->isSingleWordQuery('ионы - это'))->toBeFalse();
});

test('does not find a word inside another word', function () {
    $matches = $this->textProcessor->lexicalMatches([
        ['payload' => ['document_id' => 1, 'original_name' => 'chemistry.pdf', 'page' => 1, 'text' => 'Миллионы частиц.']],
        ['payload' => ['document_id' => 1, 'original_name' => 'chemistry.pdf', 'page' => 2, 'text' => 'Ионы находятся в растворе.']],
    ], 'ионы');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['page'])->toBe(2);
});

test('ranks phrase matches before partial matches', function () {
    $matches = $this->textProcessor->lexicalMatches([
        ['payload' => ['document_id' => 2, 'original_name' => 'second.pdf', 'page' => 9, 'text' => 'Отбор помогает популяции приспосабливаться.']],
        ['payload' => ['document_id' => 1, 'original_name' => 'first.pdf', 'page' => 4, 'text' => 'Естественный отбор изменяет частоты признаков в популяции.']],
    ], 'естественный отбор');

    expect($matches)->toHaveCount(2)
        ->and($matches[0]['document_name'])->toBe('first.pdf')
        ->and($matches[0]['phrase_matched'])->toBeTrue()
        ->and($matches[0]['matched_terms'])->toBe(['естественный', 'отбор'])
        ->and($matches[1]['document_name'])->toBe('second.pdf')
        ->and($matches[1]['phrase_matched'])->toBeFalse();
});
