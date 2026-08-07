<?php

namespace App\Models\Concerns;

use App\Enums\UserLogAction;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a model to the user log.
 *
 * Only changes made by a signed-in user are recorded, so anything the seeders,
 * console commands or the employee portal write stays out of it.
 *
 * Models may override userLogLabel() to name themselves better than their
 * primary key, and userLogIgnored() to keep churn-prone columns out.
 */
trait RecordsUserActivity
{
    public static function bootRecordsUserActivity(): void
    {
        static::created(function (Model $model): void {
            UserLog::record(UserLogAction::Created, $model);
        });

        static::updated(function (Model $model): void {
            UserLog::record(UserLogAction::Updated, $model);
        });

        static::deleted(function (Model $model): void {
            UserLog::record(UserLogAction::Deleted, $model);
        });
    }

    /**
     * How this record is named in the log.
     */
    public function userLogLabel(): string
    {
        foreach (['name', 'plate_number', 'email', 'title'] as $attribute) {
            if (filled($this->getAttribute($attribute))) {
                return (string) $this->getAttribute($attribute);
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }

    /**
     * Attributes that should never appear in the log.
     *
     * @return list<string>
     */
    public function userLogIgnored(): array
    {
        return [];
    }
}
