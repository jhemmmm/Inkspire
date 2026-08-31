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
        AuditLogger::recordMutation('created', $model, null, $model->getAttributes());
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        AuditLogger::recordMutation(
            'updated',
            $model,
            array_intersect_key($model->getOriginal(), $model->getChanges()),
            $model->getChanges(),
        );
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        AuditLogger::recordMutation('deleted', $model, $model->getAttributes(), null);
    }
}
