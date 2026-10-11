<?php

use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('イベント一覧')] class extends Component {
    use WithPagination;

    /**
     * Get the published events that have not ended yet.
     *
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return Event::published()
            ->where('ends_at', '>', now())
            ->withMin('ticketTypes', 'price')
            ->orderBy('starts_at')
            ->paginate(12);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl" level="1">イベント一覧</flux:heading>
        <flux:subheading>開催予定のイベントです</flux:subheading>
    </div>

    @if ($this->events->isEmpty())
        <flux:text>現在公開中のイベントはありません。</flux:text>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->events as $event)
                <a
                    wire:key="event-{{ $event->id }}"
                    href="{{ route('events.show', $event) }}"
                    wire:navigate
                    class="block overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:border-zinc-400 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                >
                    <div class="relative aspect-video">
                        <x-placeholder-pattern class="absolute inset-0 size-full stroke-zinc-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    <div class="space-y-2 p-4">
                        <flux:heading size="lg" level="2" class="line-clamp-2">{{ $event->title }}</flux:heading>

                        <flux:text class="flex items-center gap-1.5">
                            <flux:icon.calendar-days variant="micro" />
                            {{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}
                        </flux:text>

                        <flux:text class="flex items-center gap-1.5">
                            <flux:icon.map-pin variant="micro" />
                            {{ $event->venue }}
                        </flux:text>

                        <p class="pt-1 text-lg font-semibold text-zinc-900 dark:text-white">
                            @if ($event->ticket_types_min_price === null)
                                価格未定
                            @elseif ((int) $event->ticket_types_min_price === 0)
                                無料
                            @else
                                {{ number_format($event->ticket_types_min_price) }}円〜
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            <flux:pagination :paginator="$this->events" />
        </div>
    @endif
</section>
