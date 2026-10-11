<?php

use App\Livewire\Forms\EventForm;
use App\Models\Event;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント登録')] class extends Component {
    public EventForm $form;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', Event::class);
    }

    /**
     * Store the event as a draft.
     */
    public function save(): void
    {
        $this->authorize('create', Event::class);

        $validated = $this->form->validate();

        $event = Auth::user()->events()->create($validated);

        Flux::toast(variant: 'success', text: '下書きとして保存しました。');

        $this->redirectRoute('organizer.events.edit', $event, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading size="xl" level="1">イベント登録</flux:heading>
    <flux:subheading class="mb-6">下書きとして保存されます。公開は編集画面から行います。</flux:subheading>

    <form wire:submit="save" class="space-y-6">
        @include('partials.event-form-fields')

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">保存</flux:button>
            <flux:button variant="ghost" :href="route('organizer.events.index')" wire:navigate>キャンセル</flux:button>
        </div>
    </form>
</section>
