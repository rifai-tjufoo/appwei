<?php

namespace App\Enums;

enum DelayType: string
{
    case PerMessage = 'per_message';
    case PerBatch = 'per_batch';

    public function label(): string
    {
        return match ($this) {
            self::PerMessage => 'Per Message (mis: 1 pesan tiap 10 detik)',
            self::PerBatch => 'Per Batch (mis: 10 pesan tiap 30 detik)',
        };
    }
}
