<?php

use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('注文完了')] class extends Component {
    public Order $order;

    /**
     * Mount the component.
     */
    public function mount(Order $order): void
    {
        $this->authorize('view', $order);

        $this->order = $order->load('event');
    }
}; ?>

<section class="mx-auto w-full max-w-xl">
    <flux:callout variant="success" icon="check-circle" class="mb-6" heading="注文を受け付けました（支払いは平日課題で実装）" />

    <dl class="mb-6 grid gap-3 sm:grid-cols-[6rem_1fr]">
        <dt class="text-sm text-zinc-500">注文番号</dt>
        <dd>{{ $order->id }}</dd>

        <dt class="text-sm text-zinc-500">イベント</dt>
        <dd>{{ $order->event->title }}</dd>

        <dt class="text-sm text-zinc-500">合計</dt>
        <dd>{{ number_format($order->total_amount) }}円</dd>
    </dl>

    <flux:button :href="route('home')" wire:navigate>イベント一覧へ戻る</flux:button>
</section>
