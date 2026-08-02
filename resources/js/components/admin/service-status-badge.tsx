import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { AttendanceStatus, ServiceLifecycleStatus } from './service-operation-types';

const serviceLabels: Record<ServiceLifecycleStatus, string> = {
    SCHEDULED: 'Scheduled',
    AWAITING_COMPLETION: 'Needs completion',
    COMPLETED: 'Completed',
    NOT_OPERATED: 'Not operated',
};

const serviceClasses: Record<ServiceLifecycleStatus, string> = {
    SCHEDULED: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
    AWAITING_COMPLETION: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    COMPLETED: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    NOT_OPERATED: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
};

const attendanceLabels: Record<AttendanceStatus, string> = {
    PENDING: 'Unmarked',
    BOARDED: 'Boarded',
    NO_SHOW: 'No-show',
    SERVICE_NOT_OPERATED: 'Service not operated',
};

const attendanceClasses: Record<AttendanceStatus, string> = {
    PENDING: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
    BOARDED: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    NO_SHOW: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
    SERVICE_NOT_OPERATED: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200',
};

export function ServiceStatusBadge({ status, className }: { status: ServiceLifecycleStatus; className?: string }) {
    return (
        <Badge variant="outline" className={cn(serviceClasses[status], className)}>
            {serviceLabels[status]}
        </Badge>
    );
}

export function AttendanceStatusBadge({ status }: { status: AttendanceStatus }) {
    return (
        <Badge variant="outline" className={attendanceClasses[status]}>
            {attendanceLabels[status]}
        </Badge>
    );
}
