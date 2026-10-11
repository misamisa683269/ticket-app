<?php

use App\Enums\EventStatus;
use App\Livewire\Forms\EventForm;
use App\Livewire\Forms\TicketTypeForm;
use App\Models\Event;
use App\Models\TicketType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント編集')] class extends Component {
    public Event $event;

    public EventForm $form;

    public TicketTypeForm $ticketTypeForm;

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

        if (! $this->event->isPublished() && $this->event->ticketTypes()->doesntExist()) {
            Flux::toast(variant: 'danger', text: '公開するには券種を1つ以上追加してください。');

            return;
        }

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
     * Get the ticket types of the event.
     *
     * @return Collection<int, TicketType>
     */
    #[Computed]
    public function ticketTypes(): Collection
    {
        return $this->event->ticketTypes()->orderBy('id')->get();
    }

    /**
     * Create a new ticket type, or update the one being edited.
     */
    public function saveTicketType(): void
    {
        $this->authorize('update', $this->event);

        $attributes = $this->ticketTypeForm->payload();

        if ($this->ticketTypeForm->isEditing()) {
            $ticketType = $this->event->ticketTypes()->find($this->ticketTypeForm->ticketTypeId);

            if ($ticketType === null) {
                $this->ticketTypeForm->resetForm();
                unset($this->ticketTypes);

                Flux::toast(variant: 'danger', text: 'この券種は既に削除されています。');

                return;
            }

            $ticketType->update($attributes);
            $message = '券種を更新しました。';
        } else {
            $this->event->ticketTypes()->create($attributes);
            $message = '券種を追加しました。';
        }

        $this->ticketTypeForm->resetForm();
        unset($this->ticketTypes);

        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * Load a ticket type into the form for editing.
     */
    public function editTicketType(int $ticketTypeId): void
    {
        $this->authorize('update', $this->event);

        $this->ticketTypeForm->setTicketType($this->findTicketType($ticketTypeId));
    }

    /**
     * Stop editing and return the form to the "add" state.
     */
    public function cancelTicketTypeEdit(): void
    {
        $this->ticketTypeForm->resetForm();
    }

    /**
     * Delete a ticket type.
     *
     * A published event must keep at least one ticket type.
     */
    public function deleteTicketType(int $ticketTypeId): void
    {
        $this->authorize('update', $this->event);

        $ticketType = $this->findTicketType($ticketTypeId);

        if ($this->event->isPublished() && $this->event->ticketTypes()->count() === 1) {
            Flux::toast(variant: 'danger', text: '公開中のイベントの最後の券種は削除できません。');

            return;
        }

        $ticketType->delete();

        if ($this->ticketTypeForm->ticketTypeId === $ticketType->id) {
            $this->ticketTypeForm->resetForm();
        }

        unset($this->ticketTypes);

        Flux::toast(variant: 'success', text: '券種を削除しました。');
    }

    /**
     * Find a ticket type that belongs to this event.
     */
    private function findTicketType(int $ticketTypeId): TicketType
    {
        return $this->event->ticketTypes()->findOrFail($ticketTypeId);
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

    <div class="space-y-6">
        <flux:heading size="lg">券種</flux:heading>

        @if ($this->ticketTypes->isEmpty())
            <flux:text>券種がありません。公開するには券種を1つ以上追加してください。</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 pe-4 font-medium">券種名</th>
                            <th class="py-2 pe-4 text-right font-medium">価格</th>
                            <th class="py-2 pe-4 text-right font-medium">定員</th>
                            <th class="py-2 pe-4 font-medium">販売期間</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->ticketTypes as $ticketType)
                            <tr wire:key="ticket-type-{{ $ticketType->id }}">
                                <td class="py-2 pe-4">{{ $ticketType->name }}</td>
                                <td class="py-2 pe-4 text-right whitespace-nowrap">{{ number_format($ticketType->price) }}円</td>
                                <td class="py-2 pe-4 text-right whitespace-nowrap">{{ number_format($ticketType->capacity) }}</td>
                                <td class="py-2 pe-4 whitespace-nowrap">
                                    {{ $ticketType->sales_start_at->isoFormat('M月D日(ddd) HH:mm') }}
                                    〜
                                    {{ $ticketType->sales_end_at->isoFormat('M月D日(ddd) HH:mm') }}
                                </td>
                                <td class="py-2">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" wire:click="editTicketType({{ $ticketType->id }})">編集</flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            wire:click="deleteTicketType({{ $ticketType->id }})"
                                            wire:confirm="「{{ $ticketType->name }}」を削除しますか？"
                                        >削除</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form wire:submit="saveTicketType" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>{{ $ticketTypeForm->isEditing() ? '券種の編集' : '券種の追加' }}</flux:heading>

            <flux:input wire:model="ticketTypeForm.name" label="券種名" placeholder="一般" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="ticketTypeForm.price" type="number" min="0" label="価格（円）" required />
                <flux:input wire:model="ticketTypeForm.capacity" type="number" min="1" label="定員" required />
                <flux:input wire:model="ticketTypeForm.sales_start_at" type="datetime-local" label="販売開始" required />
                <flux:input wire:model="ticketTypeForm.sales_end_at" type="datetime-local" label="販売終了" required />
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ $ticketTypeForm->isEditing() ? '券種を更新' : '券種を追加' }}
                </flux:button>
                @if ($ticketTypeForm->isEditing())
                    <flux:button variant="ghost" wire:click="cancelTicketTypeEdit">キャンセル</flux:button>
                @endif
            </div>
        </form>
    </div>

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
