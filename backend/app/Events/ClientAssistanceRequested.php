<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Table;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientAssistanceRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Table $table;

    public string $assistanceStatus;

    public function __construct(Table $table, string $assistanceStatus)
    {
        $this->table = $table;
        $this->assistanceStatus = $assistanceStatus;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->table->restaurant_id),
            new Channel('restaurant.' . $this->table->restaurant_id . '.assistance'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'client.assistance';
    }

    public function broadcastWith(): array
    {
        return [
            'table_id' => $this->table->id,
            'table_number' => $this->table->number,
            'restaurant_id' => $this->table->restaurant_id,
            'assistance_status' => $this->assistanceStatus,
            'assistance_requested_at' => $this->table->assistance_requested_at?->toIso8601String(),
            'broadcast_at' => now()->toIso8601String(),
        ];
    }
}
