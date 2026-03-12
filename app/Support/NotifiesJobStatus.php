<?php

namespace App\Support;

use App\Models\Notification;
use Illuminate\Support\Str;
use ReflectionException;
use ReflectionProperty;

trait NotifiesJobStatus
{
    protected function notifyJobFinished(): void
    {
        $this->createNotification('Finished');
    }

    protected function notifyJobFailed(): void
    {
        $this->createNotification('Failed');
    }

    private function createNotification(string $prefix): void
    {
        if ($this->isNotificationSuppressed()) {
            return;
        }

        $userId = $this->resolveUserId();
        if (!$userId) {
            return;
        }

        $label = $this->formatJobLabel();
        $customLabel = $this->resolveCustomLabel();
        if ($customLabel) {
            $label = $customLabel;
        } else {
            $context = $this->resolveContext();
            if ($context) {
                $label = trim($label . ' ' . $context);
            }
        }

        Notification::createForUser($userId, "{$prefix}: {$label}");
    }

    private function resolveUserId(): ?int
    {
        foreach (['userId', 'user_id', 'user'] as $property) {
            $value = $this->getNotificationPropertyValue($property);
            if (is_numeric($value)) {
                return (int) $value;
            }

            if (is_object($value) && property_exists($value, 'id') && is_numeric($value->id)) {
                return (int) $value->id;
            }
        }

        return null;
    }

    private function getNotificationPropertyValue(string $property): mixed
    {
        if (!property_exists($this, $property)) {
            return null;
        }

        try {
            $ref = new ReflectionProperty($this, $property);
            $ref->setAccessible(true);
            return $ref->getValue($this);
        } catch (ReflectionException) {
            return null;
        }
    }

    private function formatJobLabel(): string
    {
        $base = class_basename($this);
        $base = Str::replaceLast('Job', '', $base);
        $label = Str::of($base)->snake(' ')->replace('_', ' ')->title();

        return trim($label);
    }

    private function resolveCustomLabel(): ?string
    {
        $label = $this->getNotificationPropertyValue('notificationLabel');
        if (is_string($label) && $label !== '') {
            return $label;
        }

        foreach (['notificationLabel', 'getNotificationLabel'] as $method) {
            if (method_exists($this, $method)) {
                $value = $this->{$method}();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function resolveContext(): ?string
    {
        $context = $this->getNotificationPropertyValue('notificationContext');
        if (is_string($context) && $context !== '') {
            return $context;
        }

        foreach (['notificationContext', 'getNotificationContext'] as $method) {
            if (method_exists($this, $method)) {
                $value = $this->{$method}();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function isNotificationSuppressed(): bool
    {
        $suppressed = $this->getNotificationPropertyValue('suppressNotification');
        return $suppressed === true;
    }
}
