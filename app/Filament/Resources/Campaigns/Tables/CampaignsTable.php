<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Enums\CampaignStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customerGroup.name')
                    ->label('Group')
                    ->sortable(),
                TextColumn::make('message_type')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (CampaignStatus $state): string => $state->color()),
                TextColumn::make('total_recipients')
                    ->label('Total')
                    ->numeric(),
                TextColumn::make('sent_count')
                    ->label('Terkirim')
                    ->numeric()
                    ->color('success'),
                TextColumn::make('failed_count')
                    ->label('Gagal')
                    ->numeric()
                    ->color('danger'),
                TextColumn::make('scheduled_at')
                    ->label('Jadwal')
                    ->dateTime()
                    ->placeholder('Langsung')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CampaignStatus::cases())->mapWithKeys(
                        fn (CampaignStatus $status) => [$status->value => $status->label()]
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
