<?php

namespace App\Enums;

enum ServiceOccurrenceStatus: string
{
    case Scheduled = 'SCHEDULED';
    case AwaitingCompletion = 'AWAITING_COMPLETION';
    case Completed = 'COMPLETED';
    case NotOperated = 'NOT_OPERATED';

    public function isFinalized(): bool
    {
        return in_array($this, [self::Completed, self::NotOperated], true);
    }
}
