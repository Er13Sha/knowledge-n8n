<?php

namespace App\Enums;

enum KnowledgeDocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Indexed = 'indexed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'В очереди',
            self::Processing => 'Идёт индексация',
            self::Indexed => 'Готов к поиску',
            self::Failed => 'Ошибка индексации',
        };
    }

    public function isSearchable(): bool
    {
        return $this === self::Indexed;
    }
}
