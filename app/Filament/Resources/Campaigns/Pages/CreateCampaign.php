<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Services\CampaignService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = CampaignStatus::Draft->value;
        $data['batch_size'] = $data['batch_size'] ?? 1;

        return $data;
    }

    protected function afterCreate(): void
    {
        $campaign = $this->getRecord();
        $campaignService = app(CampaignService::class);

        try {
            if ($campaign->is_scheduled && $campaign->scheduled_at?->isFuture()) {
                $campaignService->schedule($campaign);

                return;
            }

            $campaignService->start($campaign);
        } catch (RuntimeException $exception) {
            $campaign->update(['status' => CampaignStatus::Draft]);

            Notification::make()
                ->title('Campaign dibuat, tetapi gagal dijalankan')
                ->body($exception->getMessage())
                ->danger()
                ->duration(15000)
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return CampaignResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
