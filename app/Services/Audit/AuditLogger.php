<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    public function record(string $action, ?User $actor, ?Model $subject = null, ?array $before = null, ?array $after = null): AuditEvent
    {
        return AuditEvent::create([
            'actor_id' => $actor?->id, 'actor_label' => $actor->email ?? 'system',
            'action' => $action, 'subject_type' => $subject ? Str::snake(class_basename($subject)) : 'system', 'subject_id' => $subject?->getKey(),
            'before_data' => $this->redact($before), 'after_data' => $this->redact($after),
            'request_id' => request()->attributes->get('request_id'),
            'context' => app()->runningInConsole() ? ['source' => 'console'] : ['source' => 'web', 'ip' => request()->ip()],
        ]);
    }

    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/password|token|secret|cookie|authorization/i', (string) $key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $safe;
    }
}
