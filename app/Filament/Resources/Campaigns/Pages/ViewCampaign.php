<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Services\CampaignService;
use Filament\Actions\Action;
use RuntimeException;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label('Start')
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->canStart())
                ->requiresConfirmation()
                ->action(function (CampaignService $campaignService): void {
                    $campaign = $this->getRecord();

                    try {
                        if ($campaign->is_scheduled && $campaign->scheduled_at?->isFuture()) {
                            $campaignService->schedule($campaign);
                            Notification::make()
                                ->title('Campaign dijadwalkan')
                                ->success()
                                ->send();
                        } else {
                            $campaignService->start($campaign);
                            Notification::make()
                                ->title('Campaign berjalan')
                                ->success()
                                ->send();
                        }

                        $this->refreshFormData(['status', 'started_at', 'total_recipients']);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Campaign tidak dapat dijalankan')
                            ->body($exception->getMessage())
                            ->danger()
                            ->duration(15000)
                            ->send();
                    }
                }),
            Action::make('pause')
                ->label('Pause')
                ->icon(Heroicon::OutlinedPause)
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord()->status === CampaignStatus::Running)
                ->requiresConfirmation()
                ->action(function (CampaignService $campaignService): void {
                    $campaignService->pause($this->getRecord());

                    Notification::make()
                        ->title('Campaign di-pause')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
            Action::make('resume')
                ->label('Resume')
                ->icon(Heroicon::OutlinedPlay)
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()->status === CampaignStatus::Paused)
                ->requiresConfirmation()
                ->action(function (CampaignService $campaignService): void {
                    $campaignService->resume($this->getRecord());

                    Notification::make()
                        ->title('Campaign dilanjutkan')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
            Action::make('stop')
                ->label('Stop')
                ->icon(Heroicon::OutlinedStop)
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->isControllable())
                ->requiresConfirmation()
                ->action(function (CampaignService $campaignService): void {
                    $campaignService->stop($this->getRecord());

                    Notification::make()
                        ->title('Campaign dihentikan')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'completed_at']);
                }),
            EditAction::make()
                ->visible(fn (): bool => in_array($this->getRecord()->status, [
                    CampaignStatus::Draft,
                    CampaignStatus::Scheduled,
                    CampaignStatus::Stopped,
                ], true)),
        ];
    }
}
