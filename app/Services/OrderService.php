<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Minutes a pending order keeps its tickets reserved.
     */
    public const PENDING_MINUTES = 15;

    /**
     * Maximum number of tickets per ticket type in one order.
     */
    public const MAX_QUANTITY = 10;

    /**
     * Create a pending order with its items.
     *
     * Prices are taken from the ticket types, never from the caller.
     * The user's earlier unpaid orders of the event are cancelled.
     *
     * @param  array<int|string, int|string>  $quantities  Quantities keyed by ticket type ID.
     *
     * @throws PurchaseException
     */
    public function createOrder(User $user, Event $event, array $quantities): Order
    {
        if (! $event->isPublished()) {
            throw new PurchaseException('このイベントは現在購入できません。');
        }

        if ($quantities === []) {
            throw new PurchaseException('券種を1つ以上選んでください。');
        }

        return DB::transaction(function () use ($user, $event, $quantities): Order {
            $ticketTypes = $event->ticketTypes()
                ->whereKey(array_keys($quantities))
                ->lockForUpdate()
                ->get();

            if ($ticketTypes->count() !== count($quantities)) {
                throw new PurchaseException('選択された券種が見つかりません。');
            }

            $this->cancelPendingOrders($user, $event);

            $items = $ticketTypes->map(function (TicketType $ticketType) use ($quantities): array {
                $quantity = $this->validatedQuantity($ticketType, $quantities[$ticketType->id]);

                return [
                    'ticket_type_id' => $ticketType->id,
                    'unit_price' => $ticketType->price,
                    'quantity' => $quantity,
                ];
            });

            $order = new Order([
                'total_amount' => $items->sum(fn (array $item): int => $item['unit_price'] * $item['quantity']),
            ]);
            $order->user()->associate($user);
            $order->event()->associate($event);
            $order->save();

            $order->items()->createMany($items->all());

            return $order;
        });
    }

    /**
     * Cancel the user's unpaid orders of the event.
     *
     * Pressing the button again would otherwise keep holding tickets with
     * orders nobody pays for. Called after the ticket types are locked,
     * so concurrent requests of the same user run one at a time.
     */
    private function cancelPendingOrders(User $user, Event $event): void
    {
        Order::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($event)
            ->where('status', OrderStatus::Pending)
            ->update(['status' => OrderStatus::Cancelled]);
    }

    /**
     * Check the quantity and availability of a ticket type.
     *
     * The quantity is checked here as well as on the screen,
     * because Livewire properties can be changed by the client.
     *
     * @throws PurchaseException
     */
    private function validatedQuantity(TicketType $ticketType, int|string $quantity): int
    {
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_QUANTITY],
        ]);

        if ($quantity === false) {
            throw new PurchaseException("{$ticketType->name}の枚数は1〜".self::MAX_QUANTITY.'枚で指定してください。');
        }

        if (! $ticketType->isOnSale()) {
            throw new PurchaseException("{$ticketType->name}は販売期間外です。");
        }

        $remaining = $ticketType->remaining();

        if ($remaining < $quantity) {
            throw new PurchaseException("{$ticketType->name}の残りは{$remaining}枚です。");
        }

        return $quantity;
    }
}
