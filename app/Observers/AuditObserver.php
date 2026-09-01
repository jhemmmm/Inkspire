<?php

namespace App\Observers;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        AuditLogger::recordMutation('created', $model, null, $this->redact($model, $model->getAttributes()));
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        AuditLogger::recordMutation(
            'updated',
            $model,
            $this->redact($model, array_intersect_key($model->getOriginal(), $model->getChanges())),
            $this->redact($model, $model->getChanges()),
        );
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        AuditLogger::recordMutation('deleted', $model, $this->redact($model, $model->getAttributes()), null);
    }

    /**
     * Strip the model's hidden/sensitive attributes before they are persisted
     * to the audit trail, so values like password hashes or remember tokens
     * are never written to audit_trail.old_values/new_values.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function redact(Model $model, array $attributes): array
    {
        return array_diff_key($attributes, array_flip($model->getHidden()));
    }
}
