<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\DelayType;
use App\Enums\MediaType;
use App\Enums\MessageType;
use App\Enums\SenderMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'customer_group_id',
        'sender_mode',
        'sender_id',
        'message_type',
        'message',
        'footer',
        'button_image_url',
        'media_path',
        'media_type',
        'caption',
        'buttons',
        'delay_type',
        'delay_seconds',
        'batch_size',
        'is_scheduled',
        'scheduled_at',
        'status',
        'started_at',
        'completed_at',
        'total_recipients',
        'sent_count',
        'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'sender_mode' => SenderMode::class,
            'message_type' => MessageType::class,
            'media_type' => MediaType::class,
            'delay_type' => DelayType::class,
            'status' => CampaignStatus::class,
            'buttons' => 'array',
            'is_scheduled' => 'boolean',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Sender::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function isControllable(): bool
    {
        return in_array($this->status, [
            CampaignStatus::Running,
            CampaignStatus::Paused,
            CampaignStatus::Scheduled,
        ], true);
    }

    public function canStart(): bool
    {
        return in_array($this->status, [
            CampaignStatus::Draft,
            CampaignStatus::Scheduled,
            CampaignStatus::Stopped,
        ], true);
    }
}
