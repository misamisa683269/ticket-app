<?php

use App\Enums\EventStatus;
use App\Livewire\Forms\EventForm;
use App\Models\Event;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント編集')] class extends Component {
    public Event $event;

    public EventForm $form;

    /**
     * Mount the component.
     */
    public function mount(Event $event): void
    {
        $this->authorize('update', $event);

        $this->event = $event;
        $this->form->setEvent($event);
    }

    /**
     * Update the event information.
     */
    public function save(): void
    {
        $this->authorize('update', $this->event);

        $validated = $this->form->validate();

        $this->event->update($validated);

        Flux::toast(variant: 'success', text: 'イベントを更新しました。');
    }

    /**
     * Toggle the event between draft and published.
     *
     * Events unpublished by an admin cannot be republished by the organizer.
     */
    public function togglePublish(): void
    {
        $this->authorize('update', $this->event);

        abort_if($this->event->status === EventStatus::Unpublished, 403);

        $this->event->status = $this->event->isPublished()
            ? EventStatus::Draft
            : EventStatus::Published;
        $this->event->save();

        Flux::toast(
            variant: 'success',
            text: $this->event->isPublished() ? 'イベントを公開しました。' : 'イベントを下書きに戻しました。',
        );
    }

    /**
     * Delete the event.
     */
    public function deleteEvent(): void
    {
        $this->authorize('delete', $this->event);

        $this->event->delete();

        Flux::toast(variant: 'success', text: 'イベントを削除しました。');

        $this->redirectRoute('organizer.events.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">イベント編集</flux:heading>
            <div class="mt-2">
                <flux:badge size="sm" :color="$event->status->badgeColor()">{{ $event->status->label() }}</flux:badge>
            </div>
        </div>

        @if ($event->status === EventStatus::Unpublished)
            <flux:text class="text-sm">管理者により非公開にされています。</flux:text>
        @elseif ($event->isPublished())
            <flux:button wire:click="togglePublish" icon="eye-slash">下書きに戻す</flux:button>
        @else
            <flux:button wire:click="togglePublish" variant="primary" icon="eye">公開する</flux:button>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        @include('partials.event-form-fields')

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">保存</flux:button>
            <flux:button variant="ghost" :href="route('organizer.events.index')" wire:navigate>一覧へ戻る</flux:button>
        </div>
    </form>

    <flux:separator class="my-10" />

    <div class="space-y-4">
        <flux:heading size="lg">イベントの削除</flux:heading>
        <flux:subheading>削除すると元に戻せません。</flux:subheading>

        <flux:modal.trigger name="confirm-event-deletion">
            <flux:button variant="danger">削除する</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="confirm-event-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">「{{ $event->title }}」を削除しますか？</flux:heading>
                <flux:subheading>この操作は元に戻せません。</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">キャンセル</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteEvent">削除する</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
