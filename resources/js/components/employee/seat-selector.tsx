import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { Armchair, BusFront, CircleGauge, Info, LoaderCircle, ShieldCheck, TicketCheck } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

interface SeatSelectorProps {
    scheduleId: number;
    travelDate: string;
    capacity: number;
    prioritySeats?: number[];
    unavailableSeats?: number[];
    prioritySeatCount?: number;
    occupiedSeats: number[];
    priorityStatus: 'REGULAR' | 'PRIORITY';
    routeName: string;
    departureTime: string;
    disabled?: boolean;
    /** Existing reservation to move. When set, the sheet switches seats instead of booking a new one. */
    reservation?: { id: number; seat_number: number } | null;
}

interface ReservationForm {
    [key: string]: string | number | null;
    schedule_id: number;
    travel_date: string;
    seat_number: number | null;
}

function SeatLegend({ className, label }: { className: string; label: string }) {
    return (
        <span className="text-muted-foreground flex items-center gap-2 text-xs">
            <span className={cn('size-4 rounded border', className)} />
            {label}
        </span>
    );
}

export function SeatSelector({
    scheduleId,
    travelDate,
    capacity,
    prioritySeats,
    unavailableSeats = [],
    prioritySeatCount,
    occupiedSeats,
    priorityStatus,
    routeName,
    departureTime,
    disabled = false,
    reservation = null,
}: SeatSelectorProps) {
    const [open, setOpen] = useState(false);
    const isChangingSeat = reservation !== null;
    const form = useForm<ReservationForm>({
        schedule_id: scheduleId,
        travel_date: travelDate,
        seat_number: reservation?.seat_number ?? null,
    });
    const occupiedSeatNumbers = useMemo(
        () => new Set(occupiedSeats.filter((seat) => seat !== reservation?.seat_number)),
        [occupiedSeats, reservation],
    );
    const prioritySeatNumbers = useMemo(
        () => new Set(prioritySeats ?? Array.from({ length: prioritySeatCount ?? 0 }, (_, index) => index + 1)),
        [prioritySeatCount, prioritySeats],
    );
    const unavailableSeatNumbers = useMemo(() => new Set(unavailableSeats), [unavailableSeats]);
    const rowCount = Math.ceil(capacity / 4);
    const rows = Array.from({ length: rowCount }, (_, index) => {
        const firstSeat = index * 4 + 1;
        return [firstSeat, firstSeat + 1, firstSeat + 2, firstSeat + 3];
    });

    function selectSeat(seatNumber: number): void {
        const isPrioritySeat = prioritySeatNumbers.has(seatNumber);
        const isRestricted = isPrioritySeat && priorityStatus !== 'PRIORITY';

        if (seatNumber > capacity || unavailableSeatNumbers.has(seatNumber) || occupiedSeatNumbers.has(seatNumber) || isRestricted) {
            return;
        }

        form.setData('seat_number', seatNumber);
        form.clearErrors();
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.data.seat_number === null) {
            form.setError('seat_number', 'Select an available seat to continue.');
            return;
        }

        const selectedSeat = form.data.seat_number;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                toast.success(isChangingSeat ? `You moved to seat ${selectedSeat}.` : `Seat ${selectedSeat} is reserved.`);
                form.clearErrors();
            },
        };

        if (reservation) {
            form.patch(`/employee/reservations/${reservation.id}`, options);
            return;
        }

        form.post('/employee/reservations', options);
    }

    function handleOpenChange(nextOpen: boolean): void {
        if (form.processing) {
            return;
        }

        setOpen(nextOpen);
        if (!nextOpen) {
            form.setData('seat_number', reservation?.seat_number ?? null);
            form.clearErrors();
        }
    }

    const generalError = Object.entries(form.errors).find(([field]) => field !== 'seat_number')?.[1];

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetTrigger asChild>
                <Button disabled={disabled} variant={isChangingSeat ? 'outline' : 'default'} className="w-full sm:w-auto">
                    <Armchair />
                    {isChangingSeat ? 'Change seat' : 'Choose a seat'}
                </Button>
            </SheetTrigger>
            <SheetContent className="flex h-full w-full flex-col overflow-y-auto p-0 sm:max-w-xl">
                <SheetHeader className="border-b px-5 py-5 pr-14 sm:px-7">
                    <SheetTitle>{isChangingSeat ? 'Change your shuttle seat' : 'Select your shuttle seat'}</SheetTitle>
                    <SheetDescription>
                        {routeName} · {departureTime} · {travelDate}
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="flex-1 space-y-5 px-5 py-6 sm:px-7">
                        <Alert className="border-blue-200 bg-blue-50 text-blue-950 dark:border-blue-900 dark:bg-blue-950/35 dark:text-blue-100">
                            <Info />
                            <AlertTitle>
                                {isChangingSeat ? `You are currently in seat ${reservation.seat_number}` : 'Configured priority seats are protected'}
                            </AlertTitle>
                            <AlertDescription>
                                {isChangingSeat
                                    ? 'Pick another open seat to move there. Your reservation stays confirmed the whole time — no need to cancel it first.'
                                    : 'Seats marked Priority are reserved for employees with priority access. Unavailable seats cannot be selected.'}
                            </AlertDescription>
                        </Alert>

                        <div className="flex flex-wrap gap-x-4 gap-y-2">
                            <SeatLegend className="border-primary bg-primary" label={isChangingSeat ? 'Your seat' : 'Selected'} />
                            <SeatLegend className="border-border bg-background" label="Available" />
                            <SeatLegend className="border-amber-300 bg-amber-100 dark:border-amber-800 dark:bg-amber-950" label="Priority" />
                            <SeatLegend className="border-muted bg-muted" label="Occupied" />
                            <SeatLegend className="border-muted-foreground/40 border-dashed bg-transparent" label="Unavailable" />
                        </div>

                        <div className="border-border bg-muted/30 mx-auto w-full max-w-sm rounded-[2.5rem] border-2 p-4 shadow-inner sm:p-5">
                            <div className="mb-5 grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2 rounded-t-[1.65rem] border-b bg-blue-50 p-3 dark:bg-blue-950/30">
                                <div className="bg-background col-span-2 flex h-12 items-center justify-center gap-2 rounded-xl border text-xs font-medium">
                                    <CircleGauge className="size-4" />
                                    Driver
                                </div>
                                <div className="col-span-3 flex h-12 items-center justify-center rounded-xl border border-blue-200 bg-blue-100/80 text-xs font-medium tracking-wider text-blue-900 uppercase dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
                                    <BusFront className="mr-2 size-4" />
                                    Windshield
                                </div>
                            </div>

                            <div className="space-y-3">
                                {rows.map((row, rowIndex) => (
                                    <div key={rowIndex} className="grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2">
                                        {row.map((seatNumber, seatIndex) => {
                                            const isOutsideCapacity = seatNumber > capacity;
                                            const isUnavailable = unavailableSeatNumbers.has(seatNumber);
                                            const isOccupied = occupiedSeatNumbers.has(seatNumber);
                                            const isPrioritySeat = prioritySeatNumbers.has(seatNumber);
                                            const isRestricted = isPrioritySeat && priorityStatus !== 'PRIORITY';
                                            const isSelected = form.data.seat_number === seatNumber;
                                            const isDisabled = isOutsideCapacity || isUnavailable || isOccupied || isRestricted;
                                            const gridColumnClass = seatIndex === 2 ? 'col-start-4' : undefined;
                                            const seatLabel = isOutsideCapacity
                                                ? 'No physical seat'
                                                : isUnavailable
                                                  ? `Seat ${seatNumber}, unavailable`
                                                  : isOccupied
                                                    ? `Seat ${seatNumber}, occupied`
                                                    : isRestricted
                                                      ? `Seat ${seatNumber}, priority employees only`
                                                      : `Seat ${seatNumber}, available`;

                                            return (
                                                <button
                                                    key={seatNumber}
                                                    type="button"
                                                    disabled={isDisabled}
                                                    onClick={() => selectSeat(seatNumber)}
                                                    aria-label={seatLabel}
                                                    aria-pressed={isSelected}
                                                    className={cn(
                                                        'focus-visible:ring-ring relative flex aspect-square min-h-13 items-center justify-center rounded-xl border text-sm font-bold shadow-xs transition-all focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden',
                                                        gridColumnClass,
                                                        (isUnavailable || isOutsideCapacity) &&
                                                            'border-muted-foreground/35 text-muted-foreground cursor-not-allowed border-dashed bg-transparent opacity-40 shadow-none',
                                                        !isDisabled &&
                                                            !isSelected &&
                                                            'bg-background hover:border-primary hover:text-primary hover:-translate-y-0.5 hover:shadow-md',
                                                        isPrioritySeat &&
                                                            !isUnavailable &&
                                                            !isOutsideCapacity &&
                                                            !isOccupied &&
                                                            !isSelected &&
                                                            'border-amber-300 bg-amber-100 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
                                                        isOccupied &&
                                                            'border-muted bg-muted text-muted-foreground cursor-not-allowed opacity-65 shadow-none',
                                                        isRestricted && 'cursor-not-allowed opacity-55',
                                                        isSelected &&
                                                            'border-primary bg-primary text-primary-foreground ring-primary/25 scale-105 shadow-lg ring-2',
                                                    )}
                                                >
                                                    {isPrioritySeat && !isUnavailable && !isOutsideCapacity && (
                                                        <ShieldCheck className="absolute top-1 right-1 size-3 opacity-70" />
                                                    )}
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

                        {form.errors.seat_number && <p className="text-destructive text-center text-sm font-medium">{form.errors.seat_number}</p>}
                        {generalError && <p className="text-destructive text-center text-sm font-medium">{generalError}</p>}
                    </div>

                    <SheetFooter className="bg-background sticky bottom-0 border-t px-5 py-4 sm:px-7">
                        <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Your selection</p>
                                <p className="text-lg font-semibold">
                                    {form.data.seat_number === null ? 'No seat selected' : `Seat ${form.data.seat_number}`}
                                </p>
                            </div>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={
                                    form.processing ||
                                    form.data.seat_number === null ||
                                    (isChangingSeat && form.data.seat_number === reservation.seat_number)
                                }
                            >
                                {form.processing ? <LoaderCircle className="animate-spin" /> : <TicketCheck />}
                                {isChangingSeat ? 'Move to this seat' : 'Confirm reservation'}
                            </Button>
                        </div>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
