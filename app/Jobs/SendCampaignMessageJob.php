<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Enums\RecipientStatus;
use App\Services\CampaignService;
use App\Services\WhatsAppGatewayService;
use App\Models\CampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendCampaignMessageJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $recipientId,
    ) {}

    public function handle(
        WhatsAppGatewayService $gateway,
        CampaignService $campaignService,
    ): void {
        $recipient = CampaignRecipient::query()
            ->with(['campaign.sender', 'campaign.customerGroup'])
            ->find($this->recipientId);

        if (! $recipient) {
            return;
        }

        $campaign = $recipient->campaign;

        if ($campaign->status === CampaignStatus::Stopped) {
            $recipient->update([
                'status' => RecipientStatus::Skipped,
                'error_message' => 'Campaign stopped',
            ]);

            return;
        }

        if ($campaign->status === CampaignStatus::Paused) {
            $recipient->update(['status' => RecipientStatus::Pending]);

            return;
        }

        if ($campaign->status !== CampaignStatus::Running) {
            return;
        }

        $sender = $campaignService->resolveSender($campaign);

        if (! $sender) {
            $recipient->update([
                'status' => RecipientStatus::Failed,
                'error_message' => 'No active sender available',
            ]);
            $campaign->increment('failed_count');
            $campaignService->markCompletedIfDone($campaign);

            return;
        }

        try {
            $result = $gateway->send($campaign, $recipient->phone, $sender);

            if ($result['success']) {
                $recipient->update([
                    'status' => RecipientStatus::Sent,
                    'sender_phone' => $sender->phone,
                    'api_response' => $result,
                    'error_message' => null,
                    'sent_at' => now(),
                ]);
                $campaign->increment('sent_count');
            } else {
                $recipient->update([
                    'status' => RecipientStatus::Failed,
                    'sender_phone' => $sender->phone,
                    'api_response' => $result,
                    'error_message' => $result['error_message'] ?? 'API request failed',
                ]);
                $campaign->increment('failed_count');
            }
        } catch (Throwable $exception) {
            $recipient->update([
                'status' => RecipientStatus::Failed,
                'sender_phone' => $sender->phone,
                'error_message' => $exception->getMessage(),
            ]);
            $campaign->increment('failed_count');

            throw $exception;
        } finally {
            $campaignService->markCompletedIfDone($campaign->fresh());
        }
    }
}
