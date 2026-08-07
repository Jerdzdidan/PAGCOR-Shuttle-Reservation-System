<?php

namespace App\Models;

use App\Enums\UserLogAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

/**
 * An append-only trail of the changes users make to configuration and people
 * records. Rows are written by the RecordsUserActivity trait, never by hand, and
 * can never be amended afterwards.
 */
class UserLog extends Model
{
    public $timestamps = false;

    /** Attributes that are noise in an audit trail, or must never be stored. */
    private const GLOBALLY_IGNORED = [
        'created_at',
        'updated_at',
        'password',
        'remember_token',
        'email_verified_at',
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'user_id_snapshot',
        'user_name',
        'user_email',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'before_values',
        'after_values',
        'ip_address',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('User log records are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('User log records are immutable.');
        });
    }

    /**
     * Writes one entry for a change a signed-in user just made.
     *
     * Nothing is recorded when there is no user behind the change, so seeders,
     * queued jobs and console commands never pollute the trail.
     */
    public static function record(UserLogAction $action, Model $subject): void
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            return;
        }

        [$before, $after] = self::valuesFor($action, $subject);

        if ($action === UserLogAction::Updated && $after === []) {
            return;
        }

        self::query()->create([
            'user_id' => $user->getKey(),
            'user_id_snapshot' => $user->getKey(),
            'user_name' => $user->name,
            'user_email' => $user->email,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'subject_label' => self::labelFor($subject),
            'before_values' => $before === [] ? null : $before,
            'after_values' => $after === [] ? null : $after,
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * A creation records only what it ended up with, a deletion only what was
     * lost, and an update records both sides of the fields that moved.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private static function valuesFor(
        UserLogAction $action,
        Model $subject,
    ): array {
        $ignored = self::ignoredAttributes($subject);

        if ($action === UserLogAction::Created) {
            return [[], self::readable($subject->getAttributes(), $ignored)];
        }

        if ($action === UserLogAction::Deleted) {
            return [self::readable($subject->getAttributes(), $ignored), []];
        }

        $after = self::readable($subject->getChanges(), $ignored);
        $before = self::readable(
            array_intersect_key($subject->getOriginal(), $after),
            $ignored
        );

        return [$before, $after];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $ignored
     * @return array<string, mixed>
     */
    private static function readable(array $attributes, array $ignored): array
    {
        $readable = [];

        foreach ($attributes as $attribute => $value) {
            if (in_array($attribute, $ignored, true)) {
                continue;
            }

            $readable[$attribute] = is_string($value) && Str::isJson($value)
                ? json_decode($value, true)
                : $value;
        }

        return $readable;
    }

    /** @return list<string> */
    private static function ignoredAttributes(Model $subject): array
    {
        $modelIgnored = method_exists($subject, 'userLogIgnored')
            ? $subject->userLogIgnored()
            : [];

        return [...self::GLOBALLY_IGNORED, ...$modelIgnored];
    }

    private static function labelFor(Model $subject): string
    {
        if (method_exists($subject, 'userLogLabel')) {
            return $subject->userLogLabel();
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    /** Human-readable name for the kind of record that changed. */
    public function subjectTypeLabel(): string
    {
        return Str::headline($this->subject_type);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id_snapshot' => 'integer',
            'action' => UserLogAction::class,
            'subject_id' => 'integer',
            'before_values' => 'array',
            'after_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
