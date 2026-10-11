<?php

use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Services\OrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('購入手続き')] class extends Component {
    public Event $event;

    /**
     * Selected quantities keyed by ticket type ID.
     *
     * Locked so the browser cannot change what the user confirmed.
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $quantities = [];

    /**
     * Mount the component.
     */
    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->authorize('purchase', $event);

        $this->event = $event;
        $this->quantities = session("purchase.{$event->id}", []);

        if ($this->quantities === []) {
            $this->redirectRoute('events.show', $event, navigate: true);
        }
    }

    /**
     * Get the selected ticket types with their quantity and subtotal.
     *
     * Prices are read from the database, not from the session.
     *
     * @return Collection<int, array{ticketType: \App\Models\TicketType, quantity: int, subtotal: int}>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->event->ticketTypes()
            ->whereKey(array_keys($this->quantities))
            ->orderBy('price')
            ->get()
            ->map(fn ($ticketType): array => [
                'ticketType' => $ticketType,
                'quantity' => (int) $this->quantities[$ticketType->id],
                'subtotal' => $ticketType->price * (int) $this->quantities[$ticketType->id],
            ]);
    }

    /**
     * Get the total amount of the selected tickets.
     */
    #[Computed]
    public function total(): int
    {
        return $this->items->sum('subtotal');
    }

    /**
     * Create the order and go to the complete page.
     */
    public function pay(OrderService $orderService): void
    {
        $this->authorize('purchase', $this->event);

        try {
            $order = $orderService->createOrder(Auth::user(), $this->event, $this->quantities);
        } catch (PurchaseException $e) {
            $this->addError('purchase', $e->getMessage());

            return;
        }

        session()->forget("purchase.{$this->event->id}");
        $this->quantities = [];

        $this->redirectRoute('orders.complete', $order, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('home')" wire:navigate>イベント一覧</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('events.show', $event)" wire:navigate>{{ $event->title }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>購入手続き</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="xl" level="1">購入手続き</flux:heading>
    <flux:subheading class="mb-6">
        {{ $event->title }}（{{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}）
    </flux:subheading>

    @error('purchase')
        <flux:callout variant="danger" icon="exclamation-triangle" class="mb-6" :heading="$message" />
    @enderror

    <flux:table class="mb-6">
        <flux:table.columns>
            <flux:table.column>券種</flux:table.column>
            <flux:table.column align="end">単価</flux:table.column>
            <flux:table.column align="end">枚数</flux:table.column>
            <flux:table.column align="end">小計</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->items as $item)
                <flux:table.row :key="$item['ticketType']->id">
                    <flux:table.cell>{{ $item['ticketType']->name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($item['ticketType']->price) }}円</flux:table.cell>
                    <flux:table.cell align="end">{{ $item['quantity'] }}枚</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($item['subtotal']) }}円</flux:table.cell>
                </flux:table.row>
            @endforeach

            <flux:table.row>
                <flux:table.cell colspan="3" class="font-semibold">合計</flux:table.cell>
                <flux:table.cell align="end" class="text-lg font-semibold">{{ number_format($this->total) }}円</flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>

    <div class="flex items-center justify-end gap-4">
        <flux:button variant="ghost" :href="route('events.show', $event)" wire:navigate>枚数を選び直す</flux:button>
        <flux:button variant="primary" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">支払いへ進む</flux:button>
    </div>
</section>
