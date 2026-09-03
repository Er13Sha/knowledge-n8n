<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class KnowledgeSearchInteraction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'scope_document_id',
        'mode',
        'question',
        'answer',
        'answer_status',
        'confidence',
        'citations_valid',
        'source_references',
        'feedback_rating',
        'feedback_comment',
        'feedback_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'citations_valid' => 'boolean',
            'source_references' => 'array',
            'feedback_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
