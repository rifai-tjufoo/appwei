<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\RecipientStatus;
use App\Enums\SenderMode;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Sender;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CampaignService
{
    public function prepareRecipients(Campaign $campaign): void
    {
        $customers = $campaign->customerGroup
            ->customers()
            ->orderBy('customers.id')
            ->get(['customers.id', 'customers.phone']);

        DB::transaction(function () use ($campaign, $customers) {
            $campaign->recipients()->delete();

            $index = 0;
            foreach ($customers as $customer) {
                CampaignRecipient::query()->create([
                    'campaign_id' => $campaign->id,
                    'customer_id' => $customer->id,
                    'phone' => $customer->phone,
                    'status' => RecipientStatus::Pending,
                    'queue_index' => $index,
                ]);
                $index++;
            }

            $campaign->update([
                'total_recipients' => $index,
                'sent_count' => 0,
                'failed_count' => 0,
            ]);
        });
    }

    /**
     * @return array<int, string>
     */
    public function validateBeforeStart(Campaign $campaign): array
    {
        $gateway = app(WhatsAppGatewayService::class);
        $errors = [];

        if (! AppSettings::whatsappApiKey()) {
            $errors[] = 'API Key WhatsApp belum diatur di Settings.';
        }

        if ($campaign->sender_mode === SenderMode::Fixed) {
            $sender = $campaign->sender;

            if (! $sender?->is_active) {
                $errors[] = 'Sender tidak aktif atau belum dipilih.';
            } elseif ($sender) {
                $errors = array_merge($errors, $gateway->validateCampaignSenders($sender));
            }
        } else {
            $activeSenders = Sender::query()->where('is_active', true)->get();

            if ($activeSenders->isEmpty()) {
                $errors[] = 'Tidak ada sender aktif untuk mode rotating.';
            }

            $hasConnected = $activeSenders->contains(
                fn (Sender $sender): bool => $gateway->isSenderConnected($sender->phone)
            );

            if (! $hasConnected && $activeSenders->isNotEmpty()) {
                $errors[] = 'Tidak ada sender yang status Connected di gateway. Scan ulang QR di panel provider.';
            }
        }

        return $errors;
    }

    public function start(Campaign $campaign): void
    {
        if (! $campaign->canStart()) {
            return;
        }

        $errors = $this->validateBeforeStart($campaign);

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }

        $this->prepareRecipients($campaign);

        if ($campaign->total_recipients === 0) {
            $campaign->update([
                'status' => CampaignStatus::Completed,
                'completed_at' => now(),
            ]);

            return;
        }

        $campaign->update([
            'status' => CampaignStatus::Running,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $this->dispatchJobs($campaign);
    }

    public function dispatchJobs(Campaign $campaign): void
    {
        $recipients = $campaign->recipients()
            ->whereIn('status', [RecipientStatus::Pending, RecipientStatus::Queued])
            ->orderBy('queue_index')
            ->get();

        foreach ($recipients as $recipient) {
            $delaySeconds = $this->calculateDelaySeconds($campaign, $recipient->queue_index);

            $recipient->update(['status' => RecipientStatus::Queued]);

            SendCampaignMessageJob::dispatch($recipient->id)
                ->delay(now()->addSeconds($delaySeconds));
        }
    }

    public function calculateDelaySeconds(Campaign $campaign, int $queueIndex): int
    {
        if ($campaign->delay_type->value === 'per_batch') {
            $batchSize = max(1, (int) $campaign->batch_size);
            $batchNumber = intdiv($queueIndex, $batchSize);

            return $batchNumber * (int) $campaign->delay_seconds;
        }

        return $queueIndex * (int) $campaign->delay_seconds;
    }

    public function pause(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Running) {
            return;
        }

        $campaign->update(['status' => CampaignStatus::Paused]);
    }

    public function resume(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Paused) {
            return;
        }

        $campaign->update(['status' => CampaignStatus::Running]);

        $pendingRecipients = $campaign->recipients()
            ->where('status', RecipientStatus::Pending)
            ->orderBy('queue_index')
            ->get();

        foreach ($pendingRecipients as $recipient) {
            $delaySeconds = $this->calculateDelaySeconds($campaign, $recipient->queue_index);

            $recipient->update(['status' => RecipientStatus::Queued]);

            SendCampaignMessageJob::dispatch($recipient->id)
                ->delay(now()->addSeconds($delaySeconds));
        }
    }

    public function stop(Campaign $campaign): void
    {
        if (! in_array($campaign->status, [CampaignStatus::Running, CampaignStatus::Paused, CampaignStatus::Scheduled], true)) {
            return;
        }

        $campaign->update([
            'status' => CampaignStatus::Stopped,
            'completed_at' => now(),
        ]);

        $campaign->recipients()
            ->whereIn('status', [RecipientStatus::Pending, RecipientStatus::Queued])
            ->update([
                'status' => RecipientStatus::Skipped,
                'error_message' => 'Campaign stopped by user',
            ]);
    }

    public function schedule(Campaign $campaign): void
    {
        if (! $campaign->is_scheduled || ! $campaign->scheduled_at) {
            $this->start($campaign);

            return;
        }

        $this->prepareRecipients($campaign);

        $campaign->update([
            'status' => CampaignStatus::Scheduled,
        ]);
    }

    public function resolveSender(Campaign $campaign): ?Sender
    {
        if ($campaign->sender_mode === SenderMode::Fixed) {
            return $campaign->sender?->is_active
                ? $campaign->sender
                : null;
        }

        return Sender::randomActive();
    }

    public function markCompletedIfDone(Campaign $campaign): void
    {
        $campaign->refresh();

        $pendingCount = $campaign->recipients()
            ->whereIn('status', [RecipientStatus::Pending, RecipientStatus::Queued])
            ->count();

        if ($pendingCount === 0 && $campaign->status === CampaignStatus::Running) {
            $campaign->update([
                'status' => CampaignStatus::Completed,
                'completed_at' => now(),
            ]);
        }
    }

    public function processDueScheduledCampaigns(): int
    {
        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where('is_scheduled', true)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            $campaign->update(['status' => CampaignStatus::Draft]);
            $this->start($campaign);
        }

        return $campaigns->count();
    }
}
