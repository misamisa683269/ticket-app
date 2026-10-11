<?php

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Event $event;

    /**
     * Selected quantities keyed by ticket type ID.
     *
     * @var array<int|string, int|string>
     */
    public array $quantities = [];

    /**
     * Mount the component.
     */
    public function mount(Event $event): void
    {
        $this->authorize('view', $event);

        $this->event = $event->load('organizer');
    }

    /**
     * Get the ticket types with their reserved quantity.
     *
     * @return Collection<int, \App\Models\TicketType>
     */
    #[Computed]
    public function ticketTypes(): Collection
    {
        return $this->event->ticketTypes()
            ->withReservedQuantity()
            ->orderBy('price')
            ->get();
    }

    /**
     * Save the selected quantities and go to the purchase page.
     *
     * Guests are sent to the login page by the auth middleware
     * and come back to the purchase page after logging in.
     */
    public function proceed(): void
    {
        $quantities = array_filter(
            array_map('intval', $this->quantities),
            fn (int $quantity): bool => $quantity > 0,
        );

        if ($quantities === []) {
            $this->addError('quantities', '券種を1つ以上選んでください。');

            return;
        }

        session()->put("purchase.{$this->event->id}", $quantities);

        $this->redirectRoute('events.purchase', $this->event);
    }

    /**
     * Get the page title.
     */
    public function title(): string
    {
        return $this->event->title;
    }
}; ?>

<section class="mx-auto w-full max-w-3xl">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('home')" wire:navigate>イベント一覧</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $event->title }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="relative mb-6 aspect-video overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <x-placeholder-pattern class="absolute inset-0 size-full stroke-zinc-900/20 dark:stroke-neutral-100/20" />
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <flux:heading size="xl" level="1">{{ $event->title }}</flux:heading>
        @unless ($event->isPublished())
            <flux:badge :color="$event->status->badgeColor()">{{ $event->status->label() }}</flux:badge>
        @endunless
    </div>

    <dl class="mb-8 grid gap-3 sm:grid-cols-[6rem_1fr]">
        <dt class="text-sm text-zinc-500">日時</dt>
        <dd>
            {{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}
            〜 {{ $event->ends_at->isoFormat('M月D日(ddd) HH:mm') }}
        </dd>

        <dt class="text-sm text-zinc-500">会場</dt>
        <dd>{{ $event->venue }}</dd>

        <dt class="text-sm text-zinc-500">主催者</dt>
        <dd>{{ $event->organizer->name }}</dd>
    </dl>

    <flux:heading size="lg" level="2" class="mb-2">イベント詳細</flux:heading>
    <p class="mb-8 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $event->description }}</p>

    <flux:heading size="lg" level="2" class="mb-2">券種</flux:heading>

    @if ($this->ticketTypes->isEmpty())
        <flux:text>券種はまだ登録されていません。</flux:text>
    @else
        <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->ticketTypes as $ticketType)
                <div wire:key="ticket-type-{{ $ticketType->id }}" class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <flux:heading>{{ $ticketType->name }}</flux:heading>
                        <flux:text size="sm">
                            販売期間:
                            {{ $ticketType->sales_start_at->isoFormat('M月D日(ddd) HH:mm') }}
                            〜 {{ $ticketType->sales_end_at->isoFormat('M月D日(ddd) HH:mm') }}
                        </flux:text>
                    </div>

                    <div class="text-end">
                        <p class="text-lg font-semibold">
                            {{ $ticketType->price === 0 ? '無料' : number_format($ticketType->price).'円' }}
                        </p>
                        <flux:text size="sm">
                            残り {{ number_format($ticketType->remaining()) }} 枚 / 定員 {{ number_format($ticketType->capacity) }} 枚
                        </flux:text>
                        @if ($ticketType->remaining() === 0)
                            <flux:badge color="red" size="sm">完売</flux:badge>
                        @elseif (! $ticketType->isOnSale())
                            <flux:badge color="zinc" size="sm">販売期間外</flux:badge>
                        @endif
                    </div>

                    @if ($event->isPublished())
                        @php($maxQuantity = min(\App\Services\OrderService::MAX_QUANTITY, $ticketType->remaining()))
                        <div class="w-full sm:w-28">
                            <flux:select
                                wire:model="quantities.{{ $ticketType->id }}"
                                :aria-label="$ticketType->name.'の枚数'"
                                :disabled="! $ticketType->isOnSale() || $maxQuantity === 0"
                            >
                                @for ($quantity = 0; $quantity <= $maxQuantity; $quantity++)
                                    <flux:select.option :value="$quantity">{{ $quantity }}枚</flux:select.option>
                                @endfor
                            </flux:select>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($event->isPublished())
            <div class="mt-4 flex flex-col items-end gap-2">
                <flux:error name="quantities" />
                <flux:button variant="primary" wire:click="proceed">購入手続きへ進む</flux:button>
            </div>
        @endif
    @endif
</section>
