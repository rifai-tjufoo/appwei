<?php

namespace App\Enums;

enum ButtonType: string
{
    case Reply = 'reply';
    case Call = 'call';
    case Url = 'url';
    case Copy = 'copy';

    public function label(): string
    {
        return match ($this) {
            self::Reply => 'Reply',
            self::Call => 'Call',
            self::Url => 'URL',
            self::Copy => 'Copy',
        };
    }
}
