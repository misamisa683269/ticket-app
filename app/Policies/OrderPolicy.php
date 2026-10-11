<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view the order.
     *
     * The buyer, the organizer of the event and admins can view it.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            || $user->id === $order->event->organizer_id
            || $user->isAdmin();
    }
}
