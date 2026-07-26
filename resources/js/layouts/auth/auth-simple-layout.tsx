import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-brand-sky relative flex min-h-svh items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-8">
            <div className="bg-brand-blue/10 absolute -top-40 -right-40 size-[32rem] rounded-full blur-3xl" />
            <div className="bg-brand-red/10 absolute -bottom-48 -left-40 size-[30rem] rounded-full blur-3xl" />
            <div className="relative z-10 w-full max-w-md">
                <div className="mb-6 text-center">
                    <Link
                        href={route('home')}
                        className="shadow-brand-blue/10 ring-brand-blue/10 inline-flex items-center gap-3 rounded-2xl bg-white px-5 py-3 shadow-lg ring-1"
                    >
                        <AppLogoIcon className="size-12 object-contain" />
                        <span className="text-left">
                            <span className="text-brand-blue block text-xs font-semibold tracking-[0.2em]">PAGCOR</span>
                            <span className="text-brand-navy block text-sm font-semibold">Shuttle Reservation System</span>
                        </span>
                    </Link>
                </div>
                <div className="shadow-brand-navy/10 rounded-3xl border border-white/70 bg-white/95 p-6 shadow-2xl backdrop-blur sm:p-8">
                    <div className="mb-7 space-y-2 text-center">
                        <h1 className="text-brand-navy text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground text-sm">{description}</p>
                    </div>
                    {children}
                </div>
                <p className="text-brand-navy/60 mt-5 text-center text-xs font-medium tracking-wide">Secure access · PAGCOR Shuttle Operations</p>
            </div>
        </div>
    );
}
