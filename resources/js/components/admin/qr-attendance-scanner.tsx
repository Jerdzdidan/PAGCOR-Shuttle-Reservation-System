import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import { Camera, IdCard, ImageUp, Keyboard, LoaderCircle, LockKeyhole, RotateCcw, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type ChangeEvent, type FormEvent } from 'react';
import { toast } from 'sonner';

type ScannerMode = 'camera' | 'upload' | 'handheld' | 'employee_id';

const scannerModes: Array<{ value: ScannerMode; label: string; icon: typeof Camera }> = [
    { value: 'camera', label: 'Camera', icon: Camera },
    { value: 'upload', label: 'Upload', icon: ImageUp },
    { value: 'handheld', label: 'Handheld', icon: Keyboard },
    { value: 'employee_id', label: 'Employee ID', icon: IdCard },
];

interface QrAttendanceScannerProps {
    occurrenceId: number;
    disabled?: boolean;
    onRecorded?: () => void;
}

function normalizedCredential(value: string): string | null {
    const trimmedValue = value.trim();
    let signedPath = trimmedValue;

    if (/^https?:\/\//i.test(trimmedValue)) {
        try {
            const url = new URL(trimmedValue);
            signedPath = `${url.pathname}${url.search}`;
        } catch {
            return null;
        }
    }

    return /^\/employee\/login\/qr\/\d{2}-\d{5}\?signature=[a-f0-9]{64}$/i.test(signedPath) ? signedPath : null;
}

export function QrAttendanceScanner({ occurrenceId, disabled = false, onRecorded }: QrAttendanceScannerProps) {
    const [mode, setMode] = useState<ScannerMode>('camera');
    const [processing, setProcessing] = useState(false);
    const [decodingImage, setDecodingImage] = useState(false);
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraSession, setCameraSession] = useState(0);
    const [cameraPermitted, setCameraPermitted] = useState(false);
    const [scannerValue, setScannerValue] = useState('');
    const [employeeCode, setEmployeeCode] = useState('');
    const [scanError, setScanError] = useState<string>();
    const videoRef = useRef<HTMLVideoElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const controlsRef = useRef<IScannerControls | null>(null);
    const processedRef = useRef(false);

    useEffect(() => {
        const isLocalhost = ['localhost', '127.0.0.1', '[::1]', '::1'].includes(window.location.hostname);
        setCameraPermitted(window.isSecureContext || isLocalhost);
    }, []);

    const submitCredential = useCallback(
        (rawValue: string): void => {
            const credential = normalizedCredential(rawValue);

            if (!credential) {
                setScanError('This is not a valid PAGCOR employee QR code.');
                processedRef.current = false;
                return;
            }

            if (processedRef.current || disabled) {
                return;
            }

            processedRef.current = true;
            controlsRef.current?.stop();
            controlsRef.current = null;
            setCameraActive(false);
            setScanError(undefined);

            router.post(
                `/admin/finished-services/${occurrenceId}/attendance/scan`,
                { credential },
                {
                    preserveScroll: true,
                    onStart: () => setProcessing(true),
                    onSuccess: () => {
                        setScannerValue('');
                        toast.success('Passenger marked as boarded.');
                        onRecorded?.();
                    },
                    onError: (errors) => {
                        const message = Object.values(errors)[0];
                        setScanError(typeof message === 'string' ? message : 'The passenger could not be boarded with this QR code.');
                    },
                    onFinish: () => {
                        setProcessing(false);
                        processedRef.current = false;
                    },
                },
            );
        },
        [disabled, occurrenceId, onRecorded],
    );

    useEffect(() => {
        if (mode !== 'camera' || !cameraPermitted || disabled) {
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setScanError('Camera scanning is unavailable in this browser. Use image upload or a handheld scanner.');
            return;
        }

        let mounted = true;
        const reader = new BrowserQRCodeReader(undefined, { delayBetweenScanAttempts: 250 });
        setCameraActive(true);
        setScanError(undefined);

        reader
            .decodeFromConstraints({ video: { facingMode: { ideal: 'environment' } }, audio: false }, videoRef.current ?? undefined, (result) => {
                if (result) {
                    submitCredential(result.getText());
                }
            })
            .then((controls) => {
                if (!mounted || processedRef.current) {
                    controls.stop();
                    return;
                }

                controlsRef.current = controls;
            })
            .catch(() => {
                if (mounted) {
                    setCameraActive(false);
                    setScanError('Camera access was unavailable. Check browser permission, or use upload or handheld mode.');
                }
            });

        return () => {
            mounted = false;
            controlsRef.current?.stop();
            controlsRef.current = null;
            BrowserQRCodeReader.releaseAllStreams();
            setCameraActive(false);
        };
    }, [cameraPermitted, cameraSession, disabled, mode, submitCredential]);

    function changeMode(nextMode: ScannerMode): void {
        controlsRef.current?.stop();
        controlsRef.current = null;
        processedRef.current = false;
        setMode(nextMode);
        setScanError(undefined);
        setScannerValue('');
        setEmployeeCode('');
    }

    async function decodeUploadedImage(event: ChangeEvent<HTMLInputElement>): Promise<void> {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setDecodingImage(true);
        setScanError(undefined);
        const imageUrl = URL.createObjectURL(file);

        try {
            const result = await new BrowserQRCodeReader().decodeFromImageUrl(imageUrl);
            submitCredential(result.getText());
        } catch {
            setScanError('No readable employee QR code was found. Try a sharper, well-lit image.');
            processedRef.current = false;
        } finally {
            URL.revokeObjectURL(imageUrl);
            setDecodingImage(false);

            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    }

    function submitHandheld(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        submitCredential(scannerValue);
    }

    function updateEmployeeCode(value: string): void {
        const digits = value.replace(/\D/g, '').slice(0, 7);
        const formatted = digits.length > 2 ? `${digits.slice(0, 2)}-${digits.slice(2)}` : digits;

        setEmployeeCode(formatted);
        setScanError(undefined);
    }

    function submitEmployeeCode(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        setScanError(undefined);

        router.post(
            `/admin/finished-services/${occurrenceId}/attendance/employee-code`,
            { employee_code: employeeCode },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => {
                    setEmployeeCode('');
                    toast.success('Passenger marked as boarded.');
                    onRecorded?.();
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    setScanError(typeof message === 'string' ? message : 'The passenger could not be boarded with this employee ID.');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="space-y-4">
            <div className="bg-muted grid grid-cols-2 rounded-xl p-1 sm:grid-cols-4" role="tablist" aria-label="Passenger boarding method">
                {scannerModes.map((item) => {
                    const Icon = item.icon;

                    return (
                        <button
                            key={item.value}
                            type="button"
                            role="tab"
                            aria-selected={mode === item.value}
                            disabled={processing || decodingImage || disabled}
                            onClick={() => changeMode(item.value)}
                            className={cn(
                                'flex items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-xs font-medium transition-all sm:text-sm',
                                mode === item.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" />
                            {item.label}
                        </button>
                    );
                })}
            </div>

            {scanError && (
                <Alert variant="destructive">
                    <AlertDescription>{scanError}</AlertDescription>
                </Alert>
            )}

            {mode === 'camera' &&
                (cameraPermitted ? (
                    <div className="space-y-3">
                        <div className="relative aspect-video overflow-hidden rounded-2xl bg-slate-950">
                            <video ref={videoRef} className="size-full object-cover" muted playsInline aria-label="Passenger QR camera preview" />
                            <div className="pointer-events-none absolute inset-0 grid place-items-center bg-black/10">
                                <div className="relative aspect-square w-40 max-w-[52%]">
                                    <span className="absolute top-0 left-0 size-9 rounded-tl-xl border-t-4 border-l-4 border-white" />
                                    <span className="absolute top-0 right-0 size-9 rounded-tr-xl border-t-4 border-r-4 border-white" />
                                    <span className="absolute bottom-0 left-0 size-9 rounded-bl-xl border-b-4 border-l-4 border-white" />
                                    <span className="absolute right-0 bottom-0 size-9 rounded-br-xl border-r-4 border-b-4 border-white" />
                                    {cameraActive && <span className="bg-brand-red absolute top-1/2 h-0.5 w-full animate-pulse" />}
                                </div>
                            </div>
                            {processing && (
                                <div className="absolute inset-0 flex items-center justify-center gap-2 bg-slate-950/75 text-sm font-medium text-white">
                                    <LoaderCircle className="size-5 animate-spin" />
                                    Recording passenger…
                                </div>
                            )}
                        </div>
                        {!cameraActive && !processing && (
                            <Button type="button" variant="outline" size="sm" onClick={() => setCameraSession((session) => session + 1)}>
                                <RotateCcw />
                                Retry camera
                            </Button>
                        )}
                    </div>
                ) : (
                    <Alert className="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
                        <LockKeyhole className="text-amber-700 dark:text-amber-300" />
                        <AlertDescription>
                            Camera access requires HTTPS on an intranet address. It works without HTTPS on localhost; image upload and handheld
                            scanning remain available here.
                        </AlertDescription>
                    </Alert>
                ))}

            {mode === 'upload' && (
                <div className="bg-muted/20 rounded-2xl border-2 border-dashed p-6 text-center">
                    <ImageUp className="text-primary mx-auto size-7" />
                    <p className="mt-3 text-sm font-semibold">Upload an employee QR image</p>
                    <p className="text-muted-foreground mt-1 text-xs">JPG, PNG, or a clear screenshot</p>
                    <Input
                        ref={fileInputRef}
                        id={`attendance-qr-upload-${occurrenceId}`}
                        type="file"
                        accept="image/*"
                        className="sr-only"
                        onChange={decodeUploadedImage}
                        disabled={processing || decodingImage || disabled}
                    />
                    <Button asChild variant="outline" size="sm" className="mt-4" disabled={processing || decodingImage || disabled}>
                        <Label htmlFor={`attendance-qr-upload-${occurrenceId}`} className="cursor-pointer">
                            {decodingImage || processing ? <LoaderCircle className="animate-spin" /> : <ImageUp />}
                            Choose image
                        </Label>
                    </Button>
                </div>
            )}

            {mode === 'handheld' && (
                <form onSubmit={submitHandheld} className="bg-muted/20 space-y-3 rounded-2xl border p-4">
                    <div className="flex items-center gap-2">
                        <ScanLine className="text-primary size-5" />
                        <div>
                            <p className="text-sm font-semibold">Scan or paste the QR value</p>
                            <p className="text-muted-foreground text-xs">Most handheld scanners submit automatically with Enter.</p>
                        </div>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            value={scannerValue}
                            onChange={(event) => setScannerValue(event.target.value)}
                            placeholder="Waiting for employee QR…"
                            autoFocus
                            autoComplete="off"
                            disabled={processing || disabled}
                        />
                        <Button type="submit" disabled={!scannerValue.trim() || processing || disabled}>
                            {processing ? <LoaderCircle className="animate-spin" /> : <ScanLine />}
                            Record
                        </Button>
                    </div>
                </form>
            )}

            {mode === 'employee_id' && (
                <form onSubmit={submitEmployeeCode} className="bg-muted/20 space-y-3 rounded-2xl border p-4">
                    <div className="flex items-center gap-2">
                        <IdCard className="text-primary size-5" />
                        <div>
                            <p className="text-sm font-semibold">Enter employee ID</p>
                            <p className="text-muted-foreground text-xs">Only a reserved, active employee can be marked as boarded.</p>
                        </div>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor={`attendance-employee-code-${occurrenceId}`}>Employee ID</Label>
                            <Input
                                id={`attendance-employee-code-${occurrenceId}`}
                                value={employeeCode}
                                onChange={(event) => updateEmployeeCode(event.target.value)}
                                placeholder="26-00001"
                                inputMode="numeric"
                                maxLength={8}
                                autoFocus
                                autoComplete="off"
                                className="font-mono tracking-wider"
                                disabled={processing || disabled}
                            />
                        </div>
                        <Button type="submit" className="sm:self-end" disabled={!/^\d{2}-\d{5}$/.test(employeeCode) || processing || disabled}>
                            {processing ? <LoaderCircle className="animate-spin" /> : <IdCard />}
                            Record boarding
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}
