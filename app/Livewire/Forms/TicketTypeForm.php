<?php

namespace App\Livewire\Forms;

use App\Models\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Locked;
use Livewire\Form;

class TicketTypeForm extends Form
{
    public const MAX_PRICE = 1_000_000;

    public const MAX_CAPACITY = 100_000;

    /**
     * Only the id is held (not the model) so a ticket type deleted elsewhere cannot 404 the component.
     */
    #[Locked]
    public ?int $ticketTypeId = null;

    public string $name = '';

    /**
     * Not typed as int because an emptied number input sends an empty string (or null).
     */
    public int|string|null $price = '';

    public int|string|null $capacity = '';

    public string $sales_start_at = '';

    public string $sales_end_at = '';

    /**
     * Fill the form from an existing ticket type for editing.
     */
    public function setTicketType(TicketType $ticketType): void
    {
        $this->resetValidation();

        $this->ticketTypeId = $ticketType->id;
        $this->name = $ticketType->name;
        $this->price = $ticketType->price;
        $this->capacity = $ticketType->capacity;
        $this->sales_start_at = $ticketType->sales_start_at->format('Y-m-d\TH:i');
        $this->sales_end_at = $ticketType->sales_end_at->format('Y-m-d\TH:i');
    }

    /**
     * Clear the form back to the "add" state.
     */
    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    /**
     * Determine if the form is editing an existing ticket type.
     */
    public function isEditing(): bool
    {
        return $this->ticketTypeId !== null;
    }

    /**
     * Validate the input and return attributes ready to be saved.
     *
     * @return array{name: string, price: int, capacity: int, sales_start_at: string, sales_end_at: string}
     */
    public function payload(): array
    {
        $validated = $this->validate();

        return [
            'name' => $validated['name'],
            'price' => (int) $validated['price'],
            'capacity' => (int) $validated['capacity'],
            'sales_start_at' => $validated['sales_start_at'],
            'sales_end_at' => $validated['sales_end_at'],
        ];
    }

    /**
     * Get the validation rules for the ticket type.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'capacity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CAPACITY],
            'sales_start_at' => ['required', 'date'],
            'sales_end_at' => ['required', 'date', 'after:sales_start_at'],
        ];
    }

    /**
     * Get the Japanese attribute names for validation messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => '券種名',
            'price' => '価格',
            'capacity' => '定員',
            'sales_start_at' => '販売開始',
            'sales_end_at' => '販売終了',
        ];
    }
}
