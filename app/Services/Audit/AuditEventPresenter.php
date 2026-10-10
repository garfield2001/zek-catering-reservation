<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;

class AuditEventPresenter
{
    public function present(AuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'actor_label' => $event->actor_label,
            'action' => $event->action,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'created_at' => $event->created_at,
            'before_data' => $this->decodeAndRedact($event->getRawOriginal('before_data')),
            'after_data' => $this->decodeAndRedact($event->getRawOriginal('after_data')),
        ];
    }

    private function decodeAndRedact(mixed $raw): ?array
    {
        if (! is_string($raw)) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $this->redact($decoded) : null;
    }

    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/password|token|secret|cookie|authorization|remember/i', (string) $key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $safe;
    }
}
