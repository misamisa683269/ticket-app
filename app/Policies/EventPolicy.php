<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view the event.
     *
     * Published events are visible to everyone, including guests.
     * Other events are visible only to the owner and admins,
     * and everyone else gets a 404 so the event's existence is not revealed.
     */
    public function view(?User $user, Event $event): Response
    {
        if ($event->isPublished()) {
            return Response::allow();
        }

        if ($user !== null && ($this->owns($user, $event) || $user->isAdmin())) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(User $user): bool
    {
        return $user->isOrganizer();
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    /**
     * Determine whether the user can force the event to be unpublished.
     */
    public function unpublish(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user is the organizer of the event.
     */
    private function owns(User $user, Event $event): bool
    {
        return $user->id === $event->organizer_id;
    }
}
