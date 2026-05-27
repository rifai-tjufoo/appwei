<?php

namespace App\Models;

use App\Enums\RecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'customer_id',
        'phone',
        'sender_phone',
        'status',
        'api_response',
        'error_message',
        'queue_index',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecipientStatus::class,
            'api_response' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
