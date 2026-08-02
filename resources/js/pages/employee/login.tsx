import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForcedLightAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import { Camera, CheckCircle2, IdCard, ImageUp, LoaderCircle, LockKeyhole, QrCode, ShieldCheck } from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type ChangeEvent, type FormEvent } from 'react';

type ScanMode = 'camera' | 'upload';

interface EmployeeLoginProps {
    status?: string;
    errors?: {
        credential?: string;
        qr?: string;
        login?: string;
        signature?: string;
        version?: string;
        employee_code?: string;
    };
}

const scanModes: Array<{ value: ScanMode; label: string; icon: typeof Camera }> = [
    { value: 'camera', label: 'Camera', icon: Camera },
    { value: 'upload', label: 'Upload', icon: ImageUp },
];

function loginError(errors: EmployeeLoginProps['errors']): string | undefined {
    return errors?.credential ?? errors?.qr ?? errors?.login ?? errors?.signature ?? errors?.version;
}

export default function EmployeeLogin({ status, errors }: EmployeeLoginProps) {
    useForcedLightAppearance();

    const [mode, setMode] = useState<ScanMode>('camera');
    const [processing, setProcessing] = useState(false);
    const [decodingImage, setDecodingImage] = useState(false);
    const [scanError, setScanError] = useState<string>();
    const [cameraSession, setCameraSession] = useState(0);
    const [cameraActive, setCameraActive] = useState(false);
    const [employeeCode, setEmployeeCode] = useState('');
    const [employeeCodeError, setEmployeeCodeError] = useState<string>();
    const videoRef = useRef<HTMLVideoElement>(null);
    const controlsRef = useRef<IScannerControls | null>(null);
    const processedRef = useRef(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const submitQr = useCallback((rawValue: string): void => {
        const signedPath = rawValue.trim();
        const isEmployeeLoginPath = /^\/employee\/login\/qr\/\d{2}-\d{5}\?signature=[a-f0-9]{64}$/i.test(signedPath);

        if (!isEmployeeLoginPath) {
            processedRef.current = false;
            setScanError('This QR code is not a valid PAGCOR employee login code.');
            return;
        }

        if (processedRef.current) {
            return;
        }

        processedRef.current = true;
        controlsRef.current?.stop();
        controlsRef.current = null;
        setCameraActive(false);
        setScanError(undefined);

        router.post(
            signedPath,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (responseErrors) => {
                    const message = Object.values(responseErrors)[0];
                    setScanError(typeof message === 'string' ? message : 'The QR code could not be verified. Please try again.');
                    processedRef.current = false;
                },
                onFinish: () => setProcessing(false),
            },
        );
    }, []);

    useEffect(() => {
        if (mode !== 'camera') {
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraActive(false);
            setScanError('Camera scanning is not available in this browser. Upload the QR image instead.');
            return;
        }

        let mounted = true;
        const reader = new BrowserQRCodeReader(undefined, { delayBetweenScanAttempts: 250 });
        setCameraActive(true);
        setScanError(undefined);

        reader
            .decodeFromConstraints({ video: { facingMode: { ideal: 'environment' } }, audio: false }, videoRef.current ?? undefined, (result) => {
                if (result) {
                    submitQr(result.getText());
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
                if (!mounted) {
                    return;
                }

                setCameraActive(false);
                setScanError('Camera access was unavailable. Allow camera permission when using localhost; other intranet addresses require HTTPS.');
            });

        return () => {
            mounted = false;
            controlsRef.current?.stop();
            controlsRef.current = null;
            BrowserQRCodeReader.releaseAllStreams();
            setCameraActive(false);
        };
    }, [cameraSession, mode, submitQr]);

    function changeMode(nextMode: ScanMode): void {
        processedRef.current = false;
        setMode(nextMode);
        setScanError(undefined);
    }

    async function uploadQr(event: ChangeEvent<HTMLInputElement>): Promise<void> {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        setDecodingImage(true);
        setScanError(undefined);
        const imageUrl = URL.createObjectURL(file);

        try {
            const result = await new BrowserQRCodeReader().decodeFromImageUrl(imageUrl);
            submitQr(result.getText());
        } catch {
            setScanError('No readable QR code was found in that image. Try a sharper, well-lit image.');
            processedRef.current = false;
        } finally {
            URL.revokeObjectURL(imageUrl);
            setDecodingImage(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    }

    function restartCamera(): void {
        processedRef.current = false;
        setCameraSession((session) => session + 1);
    }

    function updateEmployeeCode(value: string): void {
        const digits = value.replace(/\D/g, '').slice(0, 7);
        const formatted = digits.length > 2 ? `${digits.slice(0, 2)}-${digits.slice(2)}` : digits;

        setEmployeeCode(formatted);
        setEmployeeCodeError(undefined);
    }

    function submitEmployeeCode(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        setEmployeeCodeError(undefined);

        router.post(
            '/employee/login',
            { employee_code: employeeCode },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (responseErrors) => {
                    const message = responseErrors.employee_code;
                    setEmployeeCodeError(typeof message === 'string' ? message : 'The employee ID could not be verified.');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    const displayedError = scanError ?? loginError(errors);

    return (
        <>
            <Head title="Employee login" />
            <div className="bg-background grid min-h-svh lg:grid-cols-[minmax(0,0.9fr)_minmax(34rem,1.1fr)]">
                <section className="bg-brand-navy relative hidden overflow-hidden px-10 py-12 text-white lg:flex lg:flex-col xl:px-16">
                    <div className="bg-brand-blue/35 absolute -top-48 -right-40 size-[34rem] rounded-full blur-3xl" />
                    <div className="bg-brand-red/25 absolute -bottom-48 -left-40 size-[30rem] rounded-full blur-3xl" />

                    <Link href="/" className="relative z-10 flex items-center gap-3">
                        <span className="rounded-xl bg-white p-1.5 shadow-lg">
                            <AppLogoIcon className="size-11 object-contain" />
                        </span>
                        <span>
                            <span className="block text-xs font-semibold tracking-[0.22em] text-blue-200">PAGCOR</span>
                            <span className="block text-base font-semibold">Shuttle Reservation System</span>
                        </span>
                    </Link>

                    <div className="relative z-10 my-auto max-w-lg space-y-8">
                        <div className="space-y-4">
                            <p className="text-sm font-semibold tracking-[0.22em] text-blue-200 uppercase">Employee access</p>
                            <h1 className="text-4xl leading-tight font-semibold xl:text-5xl">Your daily ride, reserved in a scan.</h1>
                            <p className="max-w-md text-base leading-7 text-blue-100/70">
                                Use your unique employee QR code or employee ID to view schedules, choose a seat, and manage upcoming shuttle trips.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="rounded-2xl border border-white/10 bg-white/6 p-4">
                                <ShieldCheck className="mb-3 size-6 text-amber-300" />
                                <p className="font-semibold">Secure employee access</p>
                                <p className="mt-1 text-sm leading-6 text-blue-100/60">Every QR is signed and unique to your employee record.</p>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-white/6 p-4">
                                <CheckCircle2 className="mb-3 size-6 text-emerald-300" />
                                <p className="font-semibold">Live seat availability</p>
                                <p className="mt-1 text-sm leading-6 text-blue-100/60">Reserve a seat or join the automatic priority queue.</p>
                            </div>
                        </div>
                    </div>

                    <p className="relative z-10 text-xs text-blue-100/45">Authorized PAGCOR employees only</p>
                </section>

                <main className="relative flex min-h-svh items-center justify-center overflow-hidden px-4 py-8 sm:px-8">
                    <div className="bg-brand-blue/8 absolute -top-28 right-0 size-80 rounded-full blur-3xl" />
                    <div className="w-full max-w-xl">
                        <Link href="/" className="mb-6 flex items-center justify-center gap-3 lg:hidden">
                            <span className="ring-border rounded-xl bg-white p-1.5 shadow-md ring-1">
                                <AppLogoIcon className="size-10 object-contain" />
                            </span>
                            <span>
                                <span className="text-brand-blue block text-xs font-bold tracking-[0.2em]">PAGCOR</span>
                                <span className="text-brand-navy block text-sm font-semibold dark:text-blue-100">Employee Shuttle Portal</span>
                            </span>
                        </Link>

                        <Card className="relative overflow-hidden shadow-xl shadow-blue-950/8">
                            <div className="from-brand-blue via-brand-blue to-brand-red h-1.5 bg-linear-to-r" />
                            <CardHeader className="space-y-2 pb-4 text-center">
                                <div className="bg-brand-sky text-brand-blue mx-auto flex size-12 items-center justify-center rounded-2xl dark:bg-blue-950 dark:text-blue-300">
                                    <QrCode className="size-6" />
                                </div>
                                <CardTitle className="text-2xl">Employee login</CardTitle>
                                <CardDescription>Scan your assigned QR code or enter your employee ID.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {status && (
                                    <Alert className="border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/35 dark:text-emerald-100">
                                        <CheckCircle2 />
                                        <AlertDescription>{status}</AlertDescription>
                                    </Alert>
                                )}

                                <div className="bg-muted grid grid-cols-2 rounded-xl p-1" role="tablist" aria-label="QR login method">
                                    {scanModes.map((item) => {
                                        const Icon = item.icon;
                                        const isActive = mode === item.value;

                                        return (
                                            <button
                                                key={item.value}
                                                type="button"
                                                role="tab"
                                                aria-selected={isActive}
                                                onClick={() => changeMode(item.value)}
                                                disabled={processing || decodingImage}
                                                className={cn(
                                                    'flex items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-xs font-medium transition-all sm:text-sm',
                                                    isActive
                                                        ? 'bg-background text-foreground shadow-sm'
                                                        : 'text-muted-foreground hover:text-foreground',
                                                )}
                                            >
                                                <Icon className="size-4" />
                                                {item.label}
                                            </button>
                                        );
                                    })}
                                </div>

                                {mode === 'camera' && (
                                    <div className="space-y-3">
                                        <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-950">
                                            <video
                                                ref={videoRef}
                                                className="size-full object-cover"
                                                muted
                                                playsInline
                                                aria-label="QR camera preview"
                                            />
                                            <div className="pointer-events-none absolute inset-0 grid place-items-center bg-black/10">
                                                <div className="relative aspect-square w-52 max-w-[60%]">
                                                    <span className="absolute top-0 left-0 size-10 rounded-tl-xl border-t-4 border-l-4 border-white" />
                                                    <span className="absolute top-0 right-0 size-10 rounded-tr-xl border-t-4 border-r-4 border-white" />
                                                    <span className="absolute bottom-0 left-0 size-10 rounded-bl-xl border-b-4 border-l-4 border-white" />
                                                    <span className="absolute right-0 bottom-0 size-10 rounded-br-xl border-r-4 border-b-4 border-white" />
                                                    {cameraActive && (
                                                        <span className="bg-brand-red absolute top-1/2 h-0.5 w-full animate-pulse shadow-[0_0_12px_currentColor]" />
                                                    )}
                                                </div>
                                            </div>
                                            {processing && (
                                                <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950/75 text-white">
                                                    <LoaderCircle className="size-8 animate-spin" />
                                                    <p className="text-sm font-medium">Verifying employee QR…</p>
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-muted-foreground text-xs leading-5">
                                                Camera works on localhost without HTTPS. Other intranet addresses require HTTPS.
                                            </p>
                                            {!cameraActive && !processing && (
                                                <Button type="button" variant="outline" size="sm" onClick={restartCamera}>
                                                    <Camera />
                                                    Retry
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {mode === 'upload' && (
                                    <div className="border-border bg-muted/25 rounded-2xl border-2 border-dashed p-8 text-center">
                                        <div className="bg-background mx-auto flex size-14 items-center justify-center rounded-2xl shadow-sm">
                                            <ImageUp className="text-primary size-6" />
                                        </div>
                                        <h2 className="mt-4 font-semibold">Upload your QR image</h2>
                                        <p className="text-muted-foreground mt-1 text-sm leading-6">
                                            Choose a clear JPG, PNG, or screenshot of your employee QR.
                                        </p>
                                        <Input
                                            ref={fileInputRef}
                                            id="employee-qr-image"
                                            type="file"
                                            accept="image/*"
                                            className="sr-only"
                                            onChange={uploadQr}
                                            disabled={processing || decodingImage}
                                        />
                                        <Button asChild className="mt-5" disabled={processing || decodingImage}>
                                            <Label htmlFor="employee-qr-image" className="cursor-pointer">
                                                {processing || decodingImage ? <LoaderCircle className="animate-spin" /> : <ImageUp />}
                                                Choose QR image
                                            </Label>
                                        </Button>
                                    </div>
                                )}

                                <InputError message={displayedError} className="text-center" />

                                <div className="relative border-t pt-5">
                                    <span className="bg-card text-muted-foreground absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 px-3 text-xs font-medium uppercase">
                                        Or use employee ID
                                    </span>
                                    <form
                                        onSubmit={submitEmployeeCode}
                                        className="space-y-4 rounded-2xl border bg-blue-50/45 p-5 dark:bg-blue-950/20"
                                    >
                                        <div className="flex items-start gap-3">
                                            <span className="bg-primary/10 text-primary rounded-xl p-2.5">
                                                <IdCard className="size-5" />
                                            </span>
                                            <div>
                                                <h2 className="font-semibold">Log in with employee ID</h2>
                                                <p className="text-muted-foreground text-sm leading-6">
                                                    Enter the permanent ID printed below your QR code.
                                                </p>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="employee-code">Employee ID</Label>
                                            <Input
                                                id="employee-code"
                                                value={employeeCode}
                                                onChange={(event) => updateEmployeeCode(event.target.value)}
                                                placeholder="26-00001"
                                                inputMode="numeric"
                                                maxLength={8}
                                                autoComplete="off"
                                                className="font-mono text-base tracking-wider"
                                                disabled={processing}
                                            />
                                            <InputError message={employeeCodeError ?? errors?.employee_code} />
                                        </div>
                                        <Button type="submit" className="w-full" disabled={processing || !/^\d{2}-\d{5}$/.test(employeeCode)}>
                                            {processing ? <LoaderCircle className="animate-spin" /> : <LockKeyhole />}
                                            Log in with employee ID
                                        </Button>
                                    </form>
                                </div>

                                <div className="text-muted-foreground flex items-center justify-center gap-2 border-t pt-5 text-sm">
                                    Administrator?
                                    <Link href="/login" className="text-primary font-medium hover:underline">
                                        Use admin login
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </main>
            </div>
        </>
    );
}
