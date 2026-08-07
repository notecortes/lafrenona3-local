<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?int $restaurantId = null
    ): void {
        $instance = new self();
        $instance->performLog($action, $subjectType, $subjectId, $oldValues, $newValues, $userId, $restaurantId);
    }

    private function performLog(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?int $restaurantId = null
    ): void {
        $user = $userId !== null ? \App\Models\User::find($userId) : Auth::user();
        $resolvedRestaurantId = $restaurantId ?? ($user?->restaurant_id);

        \DB::table('audit_logs')->insert([
            'restaurant_id' => $resolvedRestaurantId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function logAction(
        string $action,
        mixed $subject,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $modelClass = get_class($subject);
        $shortType = (new \ReflectionClass($modelClass))->getShortName();

        $this->performLog(
            action: $action,
            subjectType: $shortType,
            subjectId: $subject->id ?? null,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: auth()->id(),
            restaurantId: auth()->user()?->restaurant_id
        );
    }
}
