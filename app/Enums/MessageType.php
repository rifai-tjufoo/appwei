<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Button = 'button';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Button => 'Button',
            self::Media => 'Image / File',
        };
    }
}
