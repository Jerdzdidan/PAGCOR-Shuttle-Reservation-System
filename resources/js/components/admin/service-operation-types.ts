export type ServiceLifecycleStatus = 'SCHEDULED' | 'AWAITING_COMPLETION' | 'COMPLETED' | 'NOT_OPERATED';
export type AttendanceStatus = 'PENDING' | 'BOARDED' | 'NO_SHOW' | 'SERVICE_NOT_OPERATED';
export type AttendanceRecordingMethod = 'QR_SCAN' | 'MANUAL' | 'FINALIZATION';

export interface ServiceManifestEntry {
    id?: number;
    attendance_id?: number;
    reservation_id?: number;
    shuttle_reservation_id?: number;
    employee_id?: number | null;
    employee_id_snapshot?: number;
    employee_code?: string | null;
    employee_name?: string;
    employee_email?: string;
    department?: string | null;
    priority_status?: 'REGULAR' | 'PRIORITY';
    seat_number: number;
    status?: AttendanceStatus | null;
    attendance_status?: AttendanceStatus | null;
    recording_method?: AttendanceRecordingMethod | null;
    boarded_at?: string | null;
    recorded_by?: {
        id: number | null;
        name: string;
    } | null;
    employee?: {
        employee_id: number;
        employee_code?: string;
        name: string;
        email?: string;
        department?: string | null;
        priority_status?: 'REGULAR' | 'PRIORITY';
    };
    reservation?: {
        id: number;
        seat_number: number;
        employee_id: number;
    };
}

export interface ServiceOccurrence {
    id: number;
    shuttle_schedule_id?: number;
    travel_date: string;
    status: ServiceLifecycleStatus;
    direction: 'OUTBOUND' | 'RETURN' | string;
    scheduled_departure_at: string;
    departure_time?: string;
    route_name?: string;
    route_origin?: string | null;
    route_destination?: string | null;
    origin?: string | null;
    destination?: string | null;
    plate_number?: string;
    vehicle_type?: string | null;
    driver_name?: string;
    route?: {
        id: number;
        name: string;
        origin?: string | null;
        destination?: string | null;
    };
    vehicle?: {
        id: number;
        plate_number: string;
        vehicle_type?: string | null;
    };
    driver?: {
        id: number;
        name: string;
        employee_id?: string;
    };
    effective_capacity: number;
    priority_seats?: number[];
    unavailable_seats?: number[];
    reserved_count?: number;
    reservation_count?: number;
    boarded_count?: number;
    no_show_count?: number;
    waitlist_unserved_count?: number;
    unserved_waitlist_count?: number;
    opening_odometer_km?: number | string | null;
    closing_odometer_km?: number | string | null;
    suggested_opening_odometer_km?: number | string | null;
    opening_odometer_prefill?: number | string | null;
    trip_distance_km?: number | string | null;
    distance_km?: number | string | null;
    actual_departure_at?: string | null;
    actual_arrival_at?: string | null;
    operational_notes?: string | null;
    incident_notes?: string | null;
    not_operated_reason?: string | null;
    finalized_at?: string | null;
    finalized_by_name?: string | null;
    finalized_by?: {
        id: number | null;
        name: string;
    } | null;
    finalizer?: {
        id: number | null;
        name: string;
    } | null;
    manifest?: ServiceManifestEntry[];
    attendance?: ServiceManifestEntry[];
    attendances?: ServiceManifestEntry[];
    reservations?: ServiceManifestEntry[];
    corrections?: Array<{
        id: number;
        action: string;
        reason: string;
        before_values?: Record<string, unknown> | null;
        after_values?: Record<string, unknown> | null;
        corrected_at: string;
        administrator?: {
            id: number | null;
            name: string;
        } | null;
    }>;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

export interface ServiceFilterOption {
    value: string;
    label: string;
}

export function manifestEntries(occurrence: ServiceOccurrence): ServiceManifestEntry[] {
    if (occurrence.manifest) {
        return occurrence.manifest;
    }

    if (occurrence.reservations) {
        const attendanceByReservation = new Map(
            (occurrence.attendance ?? occurrence.attendances ?? [])
                .filter((entry) => entry.reservation_id !== undefined || entry.shuttle_reservation_id !== undefined)
                .map((entry) => [entry.reservation_id ?? entry.shuttle_reservation_id, entry]),
        );

        return occurrence.reservations.map((reservation) => ({
            ...reservation,
            ...(reservation.id !== undefined ? attendanceByReservation.get(reservation.id) : undefined),
            id: reservation.id,
            reservation_id: reservation.reservation_id ?? reservation.shuttle_reservation_id ?? reservation.id,
        }));
    }

    return occurrence.attendance ?? occurrence.attendances ?? [];
}

export function manifestEmployeeName(entry: ServiceManifestEntry): string {
    return entry.employee_name ?? entry.employee?.name ?? `Employee #${manifestEmployeeIdentifier(entry)}`;
}

export function manifestEmployeeIdentifier(entry: ServiceManifestEntry): string {
    return String(
        entry.employee_code ?? entry.employee?.employee_code ?? entry.employee_id_snapshot ?? entry.employee_id ?? entry.employee?.employee_id ?? '—',
    );
}

export function manifestEmployeeDepartment(entry: ServiceManifestEntry): string | null {
    return entry.department ?? entry.employee?.department ?? null;
}

export function manifestEmployeePriority(entry: ServiceManifestEntry): 'REGULAR' | 'PRIORITY' {
    return entry.priority_status ?? entry.employee?.priority_status ?? 'REGULAR';
}

export function manifestStatus(entry: ServiceManifestEntry): AttendanceStatus {
    return entry.status ?? entry.attendance_status ?? 'PENDING';
}

export function occurrenceRouteName(occurrence: ServiceOccurrence): string {
    return occurrence.route_name ?? occurrence.route?.name ?? 'Route unavailable';
}

export function occurrencePlateNumber(occurrence: ServiceOccurrence): string {
    return occurrence.plate_number ?? occurrence.vehicle?.plate_number ?? 'Unassigned';
}

export function occurrenceDriverName(occurrence: ServiceOccurrence): string {
    return occurrence.driver_name ?? occurrence.driver?.name ?? 'Unassigned';
}

export function occurrenceRows(source: PaginatedData<ServiceOccurrence> | ServiceOccurrence[] | undefined): ServiceOccurrence[] {
    if (!source) {
        return [];
    }

    return Array.isArray(source) ? source : source.data;
}

export function isPaginated(source: PaginatedData<ServiceOccurrence> | ServiceOccurrence[] | undefined): source is PaginatedData<ServiceOccurrence> {
    return Boolean(source && !Array.isArray(source) && 'data' in source);
}
