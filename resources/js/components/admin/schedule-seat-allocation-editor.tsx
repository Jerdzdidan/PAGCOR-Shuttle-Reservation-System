import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Armchair, BusFront, CircleGauge, ShieldCheck, X } from 'lucide-react';
import { useMemo } from 'react';

export type SeatAllocation = {
    prioritySeats: number[];
    unavailableSeats: number[];
};

interface ScheduleSeatAllocationEditorProps extends SeatAllocation {
    capacity: number;
    onChange: (allocation: SeatAllocation) => void;
}

type SeatType = 'GENERAL' | 'PRIORITY' | 'UNAVAILABLE';

function sortSeatNumbers(seats: Iterable<number>): number[] {
    return [...seats].sort((left, right) => left - right);
}

function allocationLabel(type: SeatType): string {
    if (type === 'PRIORITY') {
        return 'Priority';
    }

    if (type === 'UNAVAILABLE') {
        return 'Unavailable';
    }

    return 'General';
}

function AllocationLegend({ type }: { type: SeatType }) {
    return (
        <span className="text-muted-foreground flex items-center gap-2 text-xs">
            <span
                className={cn(
                    'size-4 rounded border',
                    type === 'GENERAL' && 'bg-background',
                    type === 'PRIORITY' && 'border-amber-300 bg-amber-100 dark:border-amber-800 dark:bg-amber-950',
                    type === 'UNAVAILABLE' && 'border-muted-foreground/40 border-dashed bg-transparent',
                )}
            />
            {allocationLabel(type)}
        </span>
    );
}

export function ScheduleSeatAllocationEditor({ capacity, prioritySeats, unavailableSeats, onChange }: ScheduleSeatAllocationEditorProps) {
    const prioritySeatNumbers = useMemo(() => new Set(prioritySeats), [prioritySeats]);
    const unavailableSeatNumbers = useMemo(() => new Set(unavailableSeats), [unavailableSeats]);
    const rowCount = Math.ceil(capacity / 4);
    const rows = Array.from({ length: rowCount }, (_, index) => {
        const firstSeat = index * 4 + 1;

        return [firstSeat, firstSeat + 1, firstSeat + 2, firstSeat + 3];
    });

    function seatType(seatNumber: number): SeatType {
        if (unavailableSeatNumbers.has(seatNumber)) {
            return 'UNAVAILABLE';
        }

        if (prioritySeatNumbers.has(seatNumber)) {
            return 'PRIORITY';
        }

        return 'GENERAL';
    }

    function cycleSeat(seatNumber: number): void {
        const nextPrioritySeats = new Set(prioritySeatNumbers);
        const nextUnavailableSeats = new Set(unavailableSeatNumbers);
        const currentType = seatType(seatNumber);

        nextPrioritySeats.delete(seatNumber);
        nextUnavailableSeats.delete(seatNumber);

        if (currentType === 'GENERAL') {
            nextPrioritySeats.add(seatNumber);
        } else if (currentType === 'PRIORITY') {
            nextUnavailableSeats.add(seatNumber);
        }

        onChange({
            prioritySeats: sortSeatNumbers(nextPrioritySeats),
            unavailableSeats: sortSeatNumbers(nextUnavailableSeats),
        });
    }

    if (capacity < 1) {
        return (
            <div className="border-border bg-muted/20 rounded-xl border border-dashed px-5 py-8 text-center">
                <Armchair className="text-muted-foreground mx-auto size-6" />
                <p className="mt-3 text-sm font-medium">Select a vehicle to configure its seats.</p>
                <p className="text-muted-foreground mt-1 text-xs">The vehicle capacity or schedule override determines this layout.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                <div>
                    <p className="text-sm font-medium">Seat allocation</p>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Select a seat to cycle it from General to Priority, Unavailable, and back to General.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">{capacity - unavailableSeats.length} usable</Badge>
                    <Badge className="border-amber-200 bg-amber-100 text-amber-900 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        {prioritySeats.length} priority
                    </Badge>
                    <Badge variant="outline">{unavailableSeats.length} unavailable</Badge>
                </div>
            </div>

            <div className="flex flex-wrap gap-x-4 gap-y-2">
                <AllocationLegend type="GENERAL" />
                <AllocationLegend type="PRIORITY" />
                <AllocationLegend type="UNAVAILABLE" />
            </div>

            <div className="border-border bg-muted/30 mx-auto w-full max-w-sm rounded-[2.5rem] border-2 p-4 shadow-inner sm:p-5">
                <div className="mb-5 grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2 rounded-t-[1.65rem] border-b bg-blue-50 p-3 dark:bg-blue-950/30">
                    <div className="col-span-3 flex h-12 items-center justify-center rounded-xl border border-blue-200 bg-blue-100/80 text-xs font-medium tracking-wider text-blue-900 uppercase dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
                        <BusFront className="mr-2 size-4" />
                        Windshield
                    </div>
                    <div className="bg-background col-span-2 flex h-12 items-center justify-center gap-2 rounded-xl border text-xs font-medium">
                        <CircleGauge className="size-4" />
                        Driver
                    </div>
                </div>

                <div className="space-y-3">
                    {rows.map((row, rowIndex) => (
                        <div key={rowIndex} className="grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2">
                            {row.map((seatNumber, seatIndex) => {
                                const isOutsideCapacity = seatNumber > capacity;
                                const type = isOutsideCapacity ? 'UNAVAILABLE' : seatType(seatNumber);
                                const gridColumnClass = seatIndex === 2 ? 'col-start-4' : undefined;

                                return (
                                    <button
                                        key={seatNumber}
                                        type="button"
                                        disabled={isOutsideCapacity}
                                        onClick={() => cycleSeat(seatNumber)}
                                        aria-label={
                                            isOutsideCapacity
                                                ? 'No physical seat'
                                                : `Seat ${seatNumber}, ${allocationLabel(type)}. Select to change allocation.`
                                        }
                                        className={cn(
                                            'focus-visible:ring-ring relative flex aspect-square min-h-13 items-center justify-center rounded-xl border text-sm font-bold shadow-xs transition-all focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden',
                                            gridColumnClass,
                                            type === 'GENERAL' &&
                                                'bg-background hover:border-primary hover:text-primary hover:-translate-y-0.5 hover:shadow-md',
                                            type === 'PRIORITY' &&
                                                'border-amber-300 bg-amber-100 text-amber-950 hover:border-amber-500 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
                                            type === 'UNAVAILABLE' &&
                                                'border-muted-foreground/35 text-muted-foreground border-dashed bg-transparent shadow-none',
                                            isOutsideCapacity && 'cursor-not-allowed opacity-35',
                                        )}
                                    >
                                        {type === 'PRIORITY' && <ShieldCheck className="absolute top-1 right-1 size-3 opacity-75" />}
                                        {type === 'UNAVAILABLE' && !isOutsideCapacity && <X className="absolute top-1 right-1 size-3 opacity-75" />}
                                        {isOutsideCapacity ? '—' : seatNumber}
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>

                <div className="text-muted-foreground mt-5 flex items-center justify-center gap-2 border-t pt-4 text-xs font-medium tracking-wider uppercase">
                    <span className="bg-border h-px w-8" />
                    Rear
                    <span className="bg-border h-px w-8" />
                </div>
            </div>
        </div>
    );
}
