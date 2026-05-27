<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\CampaignStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('customerGroup.name')
                            ->label('Group Customer'),
                        TextEntry::make('sender_mode')
                            ->label('Mode Sender')
                            ->formatStateUsing(fn ($state) => $state?->label() ?? $state),
                        TextEntry::make('sender.name')
                            ->label('Sender')
                            ->placeholder('Random rotate'),
                        TextEntry::make('message_type')
                            ->label('Tipe Pesan')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (CampaignStatus $state): string => $state->color()),
                        TextEntry::make('total_recipients')
                            ->label('Total Penerima'),
                        TextEntry::make('sent_count')
                            ->label('Terkirim'),
                        TextEntry::make('failed_count')
                            ->label('Gagal'),
                        TextEntry::make('scheduled_at')
                            ->label('Jadwal')
                            ->dateTime()
                            ->placeholder('Langsung kirim'),
                        TextEntry::make('started_at')
                            ->dateTime(),
                        TextEntry::make('completed_at')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Section::make('Pesan')
                    ->schema([
                        TextEntry::make('message')
                            ->columnSpanFull(),
                        TextEntry::make('footer'),
                        TextEntry::make('caption'),
                        TextEntry::make('media_type')
                            ->badge(),
                        TextEntry::make('media_path')
                            ->label('Media'),
                    ])
                    ->columns(2),
            ]);
    }
}
