<?php

namespace App\Models;

use App\Enums\KnowledgeDocumentStatus;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'knowledge_id',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'status',
        'index_progress',
        'indexed_at',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KnowledgeDocumentStatus::class,
            'index_progress' => 'integer',
            'indexed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Knowledge, $this>
     */
    public function knowledge(): BelongsTo
    {
        return $this->belongsTo(Knowledge::class);
    }
}
