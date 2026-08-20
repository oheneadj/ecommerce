<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Payments index page, with tabs per payment status.
 */
class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /**
     * Header actions for the list page.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            ...collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [
                $status->value => Tab::make($status->label())
                    ->query(fn (Builder $query): Builder => $query->where('status', $status))
                    ->badge(Payment::query()->where('status', $status)->count()),
            ])->all(),
        ];
    }
}
