<?php

namespace App\Models;

use App\Services\OrderService;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property int $price
 * @property int $capacity
 * @property Carbon $sales_start_at
 * @property Carbon $sales_end_at
 * @property int|string|null $reserved_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'price', 'capacity', 'sales_start_at', 'sales_end_at'])]
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    /**
     * Minutes a pending order keeps its tickets reserved.
     *
     * @see OrderService::PENDING_MINUTES
     */
    public const PENDING_MINUTES = OrderService::PENDING_MINUTES;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'capacity' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
        ];
    }

    /**
     * Get the event that owns the ticket type.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the order items of the ticket type.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope a query to load the quantity held by paid and recent pending orders.
     *
     * @param  Builder<TicketType>  $query
     */
    #[Scope]
    protected function withReservedQuantity(Builder $query): void
    {
        $query->withSum([
            'orderItems as reserved_quantity' => fn (Builder $items) => $items->whereIn(
                'order_id',
                Order::query()->reservingTickets()->select('id'),
            ),
        ], 'quantity');
    }

    /**
     * Get the number of tickets still available.
     *
     * Uses the reserved_quantity loaded by withReservedQuantity() when present.
     */
    public function remaining(): int
    {
        $reserved = $this->reserved_quantity
            ?? $this->orderItems()
                ->whereIn('order_id', Order::query()->reservingTickets()->select('id'))
                ->sum('quantity');

        return max(0, $this->capacity - (int) $reserved);
    }

    /**
     * Determine if the ticket type is within its sales period.
     */
    public function isOnSale(): bool
    {
        return now()->between($this->sales_start_at, $this->sales_end_at);
    }
}
