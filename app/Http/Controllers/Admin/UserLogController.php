<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseUserLogsRequest;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserLogController extends Controller
{
    public function index(BrowseUserLogsRequest $request): Response
    {
        $filters = $request->validated();

        $logs = UserLog::query()
            ->when(
                isset($filters['date_from']),
                fn (Builder $query): Builder => $query->whereDate(
                    'occurred_at',
                    '>=',
                    $filters['date_from']
                )
            )
            ->when(
                isset($filters['date_to']),
                fn (Builder $query): Builder => $query->whereDate(
                    'occurred_at',
                    '<=',
                    $filters['date_to']
                )
            )
            ->when(
                isset($filters['action']),
                fn (Builder $query): Builder => $query->where('action', $filters['action'])
            )
            ->when(
                isset($filters['subject_type']),
                fn (Builder $query): Builder => $query->where(
                    'subject_type',
                    $filters['subject_type']
                )
            )
            ->when(
                isset($filters['user_id']),
                fn (Builder $query): Builder => $query->where(
                    'user_id_snapshot',
                    $filters['user_id']
                )
            )
            ->when(
                isset($filters['search']),
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $search): Builder => $search
                        ->where('subject_label', 'like', '%'.$filters['search'].'%')
                        ->orWhere('user_name', 'like', '%'.$filters['search'].'%')
                )
            )
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UserLog $log): array => [
                'id' => $log->getKey(),
                'occurred_at' => $log->occurred_at->toIso8601String(),
                'user' => [
                    'name' => $log->user_name,
                    'email' => $log->user_email,
                ],
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'subject_type' => $log->subject_type,
                'subject_type_label' => $log->subjectTypeLabel(),
                'subject_label' => $log->subject_label,
                'changed_fields' => $this->changedFields($log),
                'before_values' => $log->before_values,
                'after_values' => $log->after_values,
                'ip_address' => $log->ip_address,
            ]);

        return Inertia::render('admin/user-logs', [
            'logs' => $logs,
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'action' => $filters['action'] ?? null,
                'subject_type' => $filters['subject_type'] ?? null,
                'user_id' => isset($filters['user_id']) ? (int) $filters['user_id'] : null,
                'search' => $filters['search'] ?? null,
            ],
            'actions' => collect(UserLogAction::cases())
                ->map(fn (UserLogAction $action): array => [
                    'value' => $action->value,
                    'label' => $action->label(),
                ])
                ->all(),
            'subjectTypes' => $this->subjectTypes(),
            'users' => $this->users(),
        ]);
    }

    /**
     * The names of the fields that moved, ready for the table's summary column.
     *
     * @return list<string>
     */
    private function changedFields(UserLog $log): array
    {
        $fields = $log->action === UserLogAction::Deleted
            ? array_keys($log->before_values ?? [])
            : array_keys($log->after_values ?? []);

        return collect($fields)
            ->map(fn (string $field): string => Str::headline($field))
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function subjectTypes(): array
    {
        return UserLog::query()
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => Str::headline($type),
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function users(): array
    {
        return UserLog::query()
            ->whereNotNull('user_id_snapshot')
            ->groupBy('user_id_snapshot', 'user_name')
            ->orderBy('user_name')
            ->get(['user_id_snapshot', 'user_name'])
            ->map(fn (UserLog $log): array => [
                'value' => (int) $log->user_id_snapshot,
                'label' => $log->user_name,
            ])
            ->all();
    }
}
