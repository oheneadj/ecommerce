<?php

/**
 * Customer-facing saved address book — add, edit, delete, set default.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Address\CreateAddress;
use App\Actions\Address\DeleteAddress;
use App\Actions\Address\UpdateAddress;
use App\Models\Address;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My Addresses')]
class AddressBook extends Component
{
    public bool $showForm = false;

    public ?int $editingAddressId = null;

    public string $label = '';

    public string $recipient_name = '';

    public string $phone = '';

    public string $line1 = '';

    public string $line2 = '';

    public string $city = '';

    public string $region = '';

    public bool $is_default = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return Collection<int, Address>
     */
    #[Computed]
    public function addresses(): Collection
    {
        return Auth::user()->addresses()->latest('is_default')->latest('id')->get();
    }

    public function startCreate(): void
    {
        $this->reset(['editingAddressId', 'label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'region', 'is_default']);
        $this->showForm = true;
    }

    public function startEdit(int $addressId): void
    {
        $address = Address::query()->findOrFail($addressId);
        $this->authorize('update', $address);

        $this->editingAddressId = $address->id;
        $this->label = (string) $address->label;
        $this->recipient_name = $address->recipient_name;
        $this->phone = $address->phone;
        $this->line1 = $address->line1;
        $this->line2 = (string) $address->line2;
        $this->city = $address->city;
        $this->region = (string) $address->region;
        $this->is_default = $address->is_default;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['label'] = $data['label'] !== '' ? $data['label'] : null;
        $data['line2'] = $data['line2'] !== '' ? $data['line2'] : null;
        $data['region'] = $data['region'] !== '' ? $data['region'] : null;

        if ($this->editingAddressId) {
            $address = Address::query()->findOrFail($this->editingAddressId);
            $this->authorize('update', $address);

            UpdateAddress::run($address, $data);
        } else {
            CreateAddress::run(Auth::user(), $data);
        }

        unset($this->addresses);
        $this->showForm = false;
        $this->dispatch('toast', variant: 'success', message: 'Address saved.');
    }

    public function delete(int $addressId): void
    {
        $address = Address::query()->findOrFail($addressId);
        $this->authorize('delete', $address);

        DeleteAddress::run($address);

        unset($this->addresses);
        $this->dispatch('toast', variant: 'success', message: 'Address removed.');
    }

    public function setDefault(int $addressId): void
    {
        $address = Address::query()->findOrFail($addressId);
        $this->authorize('update', $address);

        UpdateAddress::run($address, ['is_default' => true]);

        unset($this->addresses);
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function render(): View
    {
        return view('livewire.storefront.address-book');
    }
}
