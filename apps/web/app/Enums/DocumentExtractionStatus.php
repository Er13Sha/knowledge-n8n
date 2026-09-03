<?php

namespace App\Enums;

enum DocumentExtractionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'В очереди',
            self::Processing => 'Обрабатывается',
            self::Completed => 'Готово',
            self::Failed => 'Ошибка',
        };
    }
}
