<?php

use App\Jobs\ProcessDocumentExtraction;
use App\Models\DocumentExtraction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('authenticated employee can upload a file for extraction', function () {
    Queue::fake();
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/extractions', [
        'document' => UploadedFile::fake()->create('notes.txt', 2, 'text/plain'),
    ]);

    $response->assertAccepted()->assertJsonPath('data.status', 'pending');
    expect(DocumentExtraction::query()->where('user_id', $user->id)->count())->toBe(1);
    Queue::assertPushed(ProcessDocumentExtraction::class);
});

test('extraction history is private to its owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    DocumentExtraction::query()->create([
        'user_id' => $owner->id,
        'original_name' => 'private.txt',
        'disk' => 'local',
        'path' => 'document-extractions/private.txt',
        'mime_type' => 'text/plain',
        'size' => 10,
        'status' => 'completed',
        'progress' => 100,
        'result' => ['text' => 'private'],
    ]);

    $this->actingAs($other)->getJson('/api/extractions')->assertJsonCount(0, 'data');
    $this->actingAs($owner)->getJson('/api/extractions')->assertJsonCount(1, 'data');
});

test('failed extraction can be retried by its owner', function () {
    Queue::fake();
    $user = User::factory()->create();
    $extraction = DocumentExtraction::query()->create([
        'user_id' => $user->id,
        'original_name' => 'broken.pdf',
        'disk' => 'local',
        'path' => 'document-extractions/broken.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
        'status' => 'failed',
        'progress' => 0,
        'error_message' => 'Ошибка OCR',
    ]);

    $this->actingAs($user)->postJson("/api/extractions/{$extraction->id}/retry")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
    Queue::assertPushed(ProcessDocumentExtraction::class, fn (ProcessDocumentExtraction $job): bool => $job->extractionId === $extraction->id);
});
