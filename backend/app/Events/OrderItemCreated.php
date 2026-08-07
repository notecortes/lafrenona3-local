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

class OrderItemCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OrderItem $orderItem;

    public function __construct(OrderItem $orderItem)
    {
        $this->orderItem = $orderItem;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->orderItem->order->restaurant_id),
            new Channel('restaurant.' . $this->orderItem->order->restaurant_id . '.kitchen'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order-item.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderItem->id,
            'order_id' => $this->orderItem->order_id,
            'product_id' => $this->orderItem->product_id,
            'quantity' => $this->orderItem->quantity,
            'unit_price' => $this->orderItem->unit_price,
            'notes' => $this->orderItem->notes,
            'status' => $this->orderItem->status,
            'target_area' => $this->orderItem->target_area,
            'created_at' => $this->orderItem->created_at,
        ];
    }
}
