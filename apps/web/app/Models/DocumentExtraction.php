<?php

namespace App\Models;

use App\Enums\DocumentExtractionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DocumentExtraction extends Model
{
    protected $fillable = [
        'user_id', 'original_name', 'disk', 'path', 'mime_type', 'detected_format',
        'size', 'status', 'progress', 'result', 'error_message', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentExtractionStatus::class,
            'size' => 'integer',
            'progress' => 'integer',
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<self> */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->is_super_admin || $user->roles()->where('key', 'admin')->exists()
            ? $query
            : $query->where('user_id', $user->id);
    }
}
