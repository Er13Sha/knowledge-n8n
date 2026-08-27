<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Knowledge extends Model
{
    /**
     * @var list<array{value: string, title: string}>
     */
    public const array DocumentTypeOptions = [
        ['value' => 'order', 'title' => 'Приказ'],
        ['value' => 'regulation', 'title' => 'Положение'],
        ['value' => 'instruction', 'title' => 'Инструкция'],
        ['value' => 'policy', 'title' => 'Политика'],
        ['value' => 'contract', 'title' => 'Договор'],
        ['value' => 'other', 'title' => 'Другое'],
    ];

    protected $fillable = [
        'user_id',
        'department_id',
        'title',
        'doc_type',
        'status',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'code');
    }

    /**
     * @return HasOne<KnowledgeDocument, $this>
     */
    public function document(): HasOne
    {
        return $this->hasOne(KnowledgeDocument::class);
    }

    public function departmentLabel(): ?string
    {
        return $this->department?->name;
    }

    public function documentTypeLabel(): ?string
    {
        foreach (self::DocumentTypeOptions as $option) {
            if ($option['value'] === $this->doc_type) {
                return $option['title'];
            }
        }

        return null;
    }
}
