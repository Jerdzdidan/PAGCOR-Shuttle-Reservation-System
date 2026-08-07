import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Armchair, BusFront, CircleGauge, ShieldCheck, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    manifestEmployeeIdentifier,
    manifestEmployeeName,
    manifestEntries,
    manifestStatus,
    type ServiceManifestEntry,
    type ServiceOccurrence,
} from './service-operation-types';
import { AttendanceStatusBadge } from './service-status-badge';

function SeatLegend({ className, label }: { className: string; label: string }) {
    return (
        <span className="text-muted-foreground flex items-center gap-2 text-xs">
            <span className={cn('size-4 rounded border', className)} />
            {label}
        </span>
    );
}

function seatClass(entry: ServiceManifestEntry | undefined, priority: boolean, unavailable: boolean): string {
    if (unavailable) {
        return 'border-muted-foreground/35 text-muted-foreground border-dashed bg-transparent shadow-none';
    }

    if (!entry) {
        return priority
            ? 'border-amber-300 bg-amber-100 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100'
            : 'bg-background';
    }

    const status = manifestStatus(entry);

    if (status === 'BOARDED') {
        return 'border-emerald-300 bg-emerald-100 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100';
    }

    if (status === 'NO_SHOW') {
        return 'border-red-300 bg-red-100 text-red-950 dark:border-red-800 dark:bg-red-950 dark:text-red-100';
    }

    if (status === 'SERVICE_NOT_OPERATED') {
        return 'border-violet-300 bg-violet-100 text-violet-950 dark:border-violet-800 dark:bg-violet-950 dark:text-violet-100';
    }

    return 'border-blue-300 bg-blue-100 text-blue-950 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100';
}

export function ServiceSeatMap({ occurrence }: { occurrence: ServiceOccurrence }) {
    const entries = manifestEntries(occurrence);
    const [selectedSeat, setSelectedSeat] = useState<number | null>(null);
    const entriesBySeat = useMemo(() => new Map(entries.map((entry) => [entry.seat_number, entry])), [entries]);
    const prioritySeats = useMemo(() => new Set(occurrence.priority_seats ?? []), [occurrence.priority_seats]);
    const unavailableSeats = useMemo(() => new Set(occurrence.unavailable_seats ?? []), [occurrence.unavailable_seats]);
    const selectedEntry = selectedSeat === null ? undefined : entriesBySeat.get(selectedSeat);
    const rows = Array.from({ length: Math.ceil(occurrence.effective_capacity / 4) }, (_, rowIndex) => {
        const firstSeat = rowIndex * 4 + 1;

        return [firstSeat, firstSeat + 1, firstSeat + 2, firstSeat + 3];
    });

    return (
        <div className="grid gap-4 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(18rem,1.2fr)]">
            <div className="border-border bg-muted/25 mx-auto w-full max-w-sm rounded-[2.25rem] border-2 p-4 shadow-inner">
                <div className="mb-4 grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2 rounded-t-[1.5rem] border-b bg-blue-50 p-3 dark:bg-blue-950/30">
                    <div className="bg-background col-span-2 flex h-11 items-center justify-center gap-2 rounded-xl border text-xs font-medium">
                        <CircleGauge className="size-4" />
                        Driver
                    </div>
                    <div className="col-span-3 flex h-11 items-center justify-center rounded-xl border border-blue-200 bg-blue-100/80 text-xs font-medium tracking-wider text-blue-900 uppercase dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
                        <BusFront className="mr-2 size-4" />
                        Windshield
                    </div>
                </div>

                <div className="space-y-2.5">
                    {rows.map((row, rowIndex) => (
                        <div key={rowIndex} className="grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2">
                            {row.map((seatNumber, seatIndex) => {
                                const outsideCapacity = seatNumber > occurrence.effective_capacity;
                                const entry = entriesBySeat.get(seatNumber);
                                const priority = prioritySeats.has(seatNumber);
                                const unavailable = unavailableSeats.has(seatNumber);

                                return (
                                    <button
                                        key={seatNumber}
                                        type="button"
                                        disabled={outsideCapacity}
                                        onClick={() => setSelectedSeat(seatNumber)}
                                        className={cn(
                                            'focus-visible:ring-ring relative flex aspect-square min-h-12 items-center justify-center rounded-xl border text-sm font-bold shadow-xs transition-all focus-visible:ring-2 focus-visible:outline-hidden',
                                            seatIndex === 2 && 'col-start-4',
                                            seatClass(entry, priority, unavailable),
                                            !outsideCapacity && 'hover:-translate-y-0.5 hover:shadow-md',
                                            selectedSeat === seatNumber && 'ring-primary/30 ring-2',
                                            outsideCapacity && 'cursor-not-allowed opacity-25',
                                        )}
                                    >
                                        {priority && <ShieldCheck className="absolute top-1 right-1 size-3 opacity-75" />}
                                        {unavailable && <X className="absolute top-1 right-1 size-3 opacity-75" />}
                                        {outsideCapacity ? '—' : seatNumber}
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </div>

            <div className="space-y-4">
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    <SeatLegend className="border-blue-300 bg-blue-100" label="Reserved" />
                    <SeatLegend className="border-emerald-300 bg-emerald-100" label="Boarded" />
                    <SeatLegend className="border-red-300 bg-red-100" label="No-show" />
                    <SeatLegend className="border-amber-300 bg-amber-100" label="Priority seat" />
                    <SeatLegend className="border-muted-foreground/40 border-dashed" label="Unavailable" />
                </div>

                <div className="bg-muted/25 flex min-h-36 items-center rounded-2xl border p-5">
                    {selectedSeat === null ? (
                        <div className="text-center sm:text-left">
                            <Armchair className="text-muted-foreground mx-auto size-6 sm:mx-0" />
                            <p className="mt-3 font-semibold">Select a seat</p>
                            <p className="text-muted-foreground mt-1 text-sm">Choose a seat in the shuttle map to inspect its passenger.</p>
                        </div>
                    ) : selectedEntry ? (
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">Seat #{selectedSeat}</Badge>
                                <AttendanceStatusBadge status={manifestStatus(selectedEntry)} />
                            </div>
                            <p className="mt-3 font-semibold">{manifestEmployeeName(selectedEntry)}</p>
                            <p className="text-muted-foreground mt-1 text-sm">Employee ID {manifestEmployeeIdentifier(selectedEntry)}</p>
                        </div>
                    ) : (
                        <div>
                            <Badge variant="outline">Seat #{selectedSeat}</Badge>
                            <p className="mt-3 font-semibold">{unavailableSeats.has(selectedSeat) ? 'Unavailable seat' : 'No reserved passenger'}</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {prioritySeats.has(selectedSeat) && !unavailableSeats.has(selectedSeat)
                                    ? 'This seat is allocated for priority passengers.'
                                    : 'This seat has no reservation for this service.'}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
