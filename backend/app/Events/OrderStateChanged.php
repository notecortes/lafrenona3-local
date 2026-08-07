<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OrderItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OrderItem $orderItem;

    public string $previousStatus;

    public function __construct(OrderItem $orderItem, string $previousStatus)
    {
        $this->orderItem = $orderItem;
        $this->previousStatus = $previousStatus;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->orderItem->order->restaurant_id),
            new Channel('restaurant.' . $this->orderItem->order->restaurant_id . '.' . $this->orderItem->target_area),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order-state.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderItem->id,
            'order_id' => $this->orderItem->order_id,
            'status' => $this->orderItem->status,
            'previous_status' => $this->previousStatus,
            'target_area' => $this->orderItem->target_area,
            'updated_at' => $this->orderItem->updated_at,
        ];
    }
}
