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
    public const array DepartmentOptions = [
        ['value' => 'management', 'title' => 'Руководство'],
        ['value' => 'hr', 'title' => 'Отдел кадров'],
        ['value' => 'finance', 'title' => 'Финансовый отдел'],
        ['value' => 'legal', 'title' => 'Юридический отдел'],
        ['value' => 'it', 'title' => 'IT-отдел'],
    ];

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

    /**
     * @return HasOne<KnowledgeDocument, $this>
     */
    public function document(): HasOne
    {
        return $this->hasOne(KnowledgeDocument::class);
    }

    public function departmentLabel(): ?string
    {
        return $this->optionLabel(self::DepartmentOptions, $this->department_id);
    }

    public function documentTypeLabel(): ?string
    {
        return $this->optionLabel(self::DocumentTypeOptions, $this->doc_type);
    }

    /**
     * @param list<array{value: string, title: string}> $options
     */
    private function optionLabel(array $options, ?string $value): ?string
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['title'];
            }
        }

        return null;
    }
}
