<?php

namespace App\Listeners;

use App\Models\Notification;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Str;
use ReflectionException;
use ReflectionProperty;

class NotifyJobFinished
{
    public function handle(JobProcessed $event): void
    {
        $job = $this->resolveJobInstance($event);
        if (!$job) {
            return;
        }

        $userId = $this->resolveUserId($job);
        if (!$userId) {
            return;
        }

        $label = $this->formatJobLabel($job);
        $customLabel = $this->resolveCustomLabel($job);
        if ($customLabel) {
            $label = $customLabel;
        } else {
            $context = $this->resolveContext($job);
            if ($context) {
                $label = trim($label . ' ' . $context);
            }
        }
        Notification::createForUser($userId, "Finished: {$label}");
    }

    private function resolveJobInstance(JobProcessed $event): ?object
    {
        $payload = $event->job?->payload();
        $command = $payload['data']['command'] ?? null;
        if (!$command) {
            return null;
        }

        try {
            $job = unserialize($command);
        } catch (\Throwable) {
            return null;
        }

        return is_object($job) ? $job : null;
    }

    private function resolveUserId(object $job): ?int
    {
        foreach (['userId', 'user_id', 'user'] as $property) {
            $value = $this->getPropertyValue($job, $property);
            if (is_numeric($value)) {
                return (int) $value;
            }

            if (is_object($value) && property_exists($value, 'id') && is_numeric($value->id)) {
                return (int) $value->id;
            }
        }

        return null;
    }

    private function getPropertyValue(object $job, string $property): mixed
    {
        if (!property_exists($job, $property)) {
            return null;
        }

        try {
            $ref = new ReflectionProperty($job, $property);
            $ref->setAccessible(true);
            return $ref->getValue($job);
        } catch (ReflectionException) {
            return null;
        }
    }

    private function formatJobLabel(object $job): string
    {
        $base = class_basename($job);
        $base = Str::replaceLast('Job', '', $base);
        $label = Str::of($base)->snake(' ')->replace('_', ' ')->title();

        return trim($label);
    }

    private function resolveCustomLabel(object $job): ?string
    {
        $label = $this->getPropertyValue($job, 'notificationLabel');
        if (is_string($label) && $label !== '') {
            return $label;
        }

        foreach (['notificationLabel', 'getNotificationLabel'] as $method) {
            if (method_exists($job, $method)) {
                $value = $job->{$method}();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function resolveContext(object $job): ?string
    {
        $context = $this->getPropertyValue($job, 'notificationContext');
        if (is_string($context) && $context !== '') {
            return $context;
        }

        foreach (['notificationContext', 'getNotificationContext'] as $method) {
            if (method_exists($job, $method)) {
                $value = $job->{$method}();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
