<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Table;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableCleared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Table $table;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->table->restaurant_id),
            new Channel('restaurant.' . $this->table->restaurant_id . '.tables'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'table.cleared';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->table->id,
            'number' => $this->table->number,
            'status' => 'free',
            'restaurant_id' => $this->table->restaurant_id,
            'cleared_at' => now()->toIso8601String(),
        ];
    }
}
