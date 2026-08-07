<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ErrorMonitoringService
{
    protected string $endpoint;

    protected string $environment;

    protected string $version;

    protected bool $enabled;

    public function __construct()
    {
        $this->endpoint = config('app.error_monitoring_endpoint', '');
        $this->environment = config('app.env', 'local');
        $this->version = config('app.version', '1.0.0');
        $this->enabled = config('app.error_monitoring_enabled', false);
    }

    public function capture(
        \Throwable $exception,
        ?string $message = null,
        ?array $context = null,
        ?string $level = 'error'
    ): array {
        if (! $this->enabled) {
            return [];
        }

        $message = $message ?? $exception->getMessage();
        $context = $context ?? [];

        $errorData = [
            'timestamp' => now()->toIso8601String(),
            'level' => $level,
            'message' => $this->sanitizeMessage($message),
            'exception' => [
                'type' => get_class($exception),
                'value' => $this->sanitizeMessage($exception->getMessage()),
                'stacktrace' => [
                    'frames' => $this->extractStackTrace($exception),
                ],
            ],
            'environment' => $this->environment,
            'release' => $this->version,
            'contexts' => [
                'trace' => [
                    'request_id' => $this->getRequestId(),
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant.context')->get(),
                ],
            ],
            'extra' => array_merge($context, [
                'url' => $this->safeRequest('fullUrl', ''),
                'method' => $this->safeRequest('method', ''),
                'ip_address' => $this->safeRequest('ip', null),
                'user_agent' => $this->safeRequest('userAgent', null),
            ]),
        ];

        $this->sendToMonitoringEndpoint($errorData);

        $this->logToDatabase($errorData);

        Log::error($message, [
            'exception_class' => get_class($exception),
            'context' => $context,
        ]);

        return $errorData;
    }

    public function captureMessage(
        string $message,
        ?string $level = 'info',
        ?array $context = null
    ): array {
        if (! $this->enabled) {
            return [];
        }

        $context = $context ?? [];

        $errorData = [
            'timestamp' => now()->toIso8601String(),
            'level' => $level,
            'message' => $this->sanitizeMessage($message),
            'environment' => $this->environment,
            'release' => $this->version,
            'contexts' => [
                'trace' => [
                    'request_id' => $this->getRequestId(),
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant.context')->get(),
                ],
            ],
            'extra' => array_merge($context, [
                'url' => $this->safeRequest('fullUrl', ''),
                'method' => $this->safeRequest('method', ''),
                'ip_address' => $this->safeRequest('ip', null),
            ]),
        ];

        $this->sendToMonitoringEndpoint($errorData);

        $this->logToDatabase($errorData);

        Log::notice($message, $context);

        return $errorData;
    }

    protected function extractStackTrace(\Throwable $exception): array
    {
        $frames = [];
        $trace = $exception->getTrace();

        foreach ($trace as $index => $frame) {
            $frames[] = [
                'filename' => $frame['file'] ?? 'unknown',
                'lineno' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'in_app' => true,
            ];
        }

        $frames[] = [
            'filename' => $exception->getFile(),
            'lineno' => $exception->getLine(),
            'function' => 'unknown',
            'in_app' => true,
        ];

        return array_reverse($frames);
    }

    protected function sendToMonitoringEndpoint(array $errorData): void
    {
        if (empty($this->endpoint)) {
            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Sentry-Auth' => 'Sentry sentry_version=7, sentry_client=laravel-saas/1.0',
                ])
                ->post($this->endpoint, [
                    'event_id' => (string) Str::uuid(),
                    'timestamp' => $errorData['timestamp'],
                    'level' => $errorData['level'],
                    'message' => $errorData['message'],
                    'exception' => $errorData['exception'] ?? null,
                    'environment' => $this->environment,
                    'release' => $this->version,
                    'contexts' => $errorData['contexts'],
                    'extra' => $errorData['extra'],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send error to monitoring endpoint.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function logToDatabase(array $errorData): void
    {
        try {
            \DB::table('audit_logs')->insert([
                'restaurant_id' => $errorData['contexts']['trace']['tenant_id'] ?? null,
                'user_id' => $errorData['contexts']['trace']['user_id'] ?? null,
                'action' => 'error_captured',
                'subject_type' => 'exception',
                'subject_id' => null,
                'old_values' => null,
                'new_values' => json_encode([
                    'level' => $errorData['level'],
                    'message' => $errorData['message'],
                    'exception_type' => $errorData['exception']['type'] ?? null,
                    'request_id' => $errorData['contexts']['trace']['request_id'] ?? null,
                ]),
                'ip_address' => $errorData['extra']['ip_address'] ?? null,
                'user_agent' => $errorData['extra']['user_agent'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log error to database.', ['error' => $e->getMessage()]);
        }
    }

    protected function getRequestId(): ?string
    {
        return $this->safeRequest('header', null, 'X-Request-ID');
    }

    protected function safeRequest(string $method, mixed $default, mixed ...$args): mixed
    {
        try {
            return request()->$method(...$args) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    protected function sanitizeMessage(string $message): string
    {
        return Str::limit($message, 500);
    }
}
