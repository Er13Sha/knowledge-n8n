<?php

use App\Models\KnowledgeSearchInteraction;
use App\Models\User;

function createSearchInteraction(User $user, array $overrides = []): KnowledgeSearchInteraction
{
    return KnowledgeSearchInteraction::query()->create(array_merge([
        'user_id' => $user->id,
        'mode' => 'rag',
        'question' => 'Какой срок действия документа?',
        'answer' => 'Срок действия — один год [1].',
        'answer_status' => 'grounded',
        'confidence' => 'medium',
        'citations_valid' => true,
        'source_references' => [['number' => 1, 'document_id' => 10, 'page' => 4]],
        'expires_at' => now()->addDays(30),
    ], $overrides));
}

test('user can rate their own search interaction', function () {
    $user = User::factory()->create();
    $interaction = createSearchInteraction($user);

    $this->actingAs($user)
        ->postJson(route('api.knowledge.search.feedback', $interaction), [
            'rating' => 'positive',
            'comment' => 'Ответ помог.',
        ])
        ->assertOk()
        ->assertJsonPath('data.rating', 'positive');

    expect($interaction->fresh()->feedback_rating)->toBe('positive')
        ->and($interaction->fresh()->feedback_comment)->toBe('Ответ помог.');
});

test('user cannot rate another users search interaction', function () {
    $user = User::factory()->create();
    $interaction = createSearchInteraction(User::factory()->create());

    $this->actingAs($user)
        ->postJson(route('api.knowledge.search.feedback', $interaction), [
            'rating' => 'negative',
        ])
        ->assertNotFound();
});

test('super admin can read search quality summary', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    createSearchInteraction($user, ['feedback_rating' => 'positive', 'feedback_at' => now()]);
    createSearchInteraction($user, [
        'answer_status' => 'citation_error',
        'confidence' => 'low',
        'citations_valid' => false,
        'question' => 'Какой срок действия документа?',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.admin.knowledge.search-quality'))
        ->assertOk()
        ->assertJsonPath('data.total_interactions', 2)
        ->assertJsonPath('data.positive_feedback', 1)
        ->assertJsonPath('data.negative_feedback', 0)
        ->assertJsonPath('data.citation_errors', 1)
        ->assertJsonPath('data.low_confidence', 1)
        ->assertJsonPath('data.popular_questions.0.total', 2);
});

test('regular user cannot read search quality summary', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.admin.knowledge.search-quality'))
        ->assertForbidden();
});
