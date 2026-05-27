<?php

namespace App\Enums;

enum SenderMode: string
{
    case Fixed = 'fixed';
    case RandomRotate = 'random_rotate';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Pilih Nomor Sender',
            self::RandomRotate => 'Rotating Random Sender',
        };
    }
}
