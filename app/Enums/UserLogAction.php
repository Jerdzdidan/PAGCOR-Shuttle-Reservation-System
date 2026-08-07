<?php

namespace App\Enums;

enum UserLogAction: string
{
    case Created = 'CREATED';
    case Updated = 'UPDATED';
    case Deleted = 'DELETED';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
        };
    }
}
