import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-brand-sky relative grid min-h-svh flex-col items-center justify-center overflow-hidden px-8 sm:px-0 lg:grid-cols-2 lg:px-0">
            <div className="bg-brand-navy relative hidden h-full min-h-svh flex-col overflow-hidden p-10 text-white lg:flex">
                <div className="bg-brand-blue/40 absolute -top-40 -right-32 size-[34rem] rounded-full blur-3xl" />
                <div className="bg-brand-red/30 absolute -bottom-48 -left-40 size-[30rem] rounded-full blur-3xl" />
                <Link href={route('home')} className="relative z-20 flex items-center gap-3 text-lg font-medium">
                    <span className="rounded-xl bg-white p-1">
                        <AppLogoIcon className="size-10 object-contain" />
                    </span>
                    <span>
                        <span className="block text-xs font-semibold tracking-[0.2em] text-blue-200">PAGCOR</span>
                        <span className="block font-semibold">Shuttle Reservation System</span>
                    </span>
                </Link>
                <div className="relative z-20 mt-auto max-w-lg space-y-5">
                    <p className="text-sm font-semibold tracking-[0.28em] text-blue-200 uppercase">PAGCOR Shuttle Operations</p>
                    <h2 className="text-4xl leading-tight font-semibold">A safer, clearer way to move the team.</h2>
                    <p className="text-base leading-7 text-blue-100/75">
                        Centralize routes, fleet configuration, driver assignments, and schedules in one trusted workspace.
                    </p>
                </div>
            </div>
            <div className="w-full lg:p-12">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={route('home')}
                        className="relative z-20 flex items-center justify-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-lg lg:hidden"
                    >
                        <AppLogoIcon className="size-10 object-contain" />
                        <span className="text-left">
                            <span className="text-brand-blue block text-xs font-semibold tracking-[0.2em]">PAGCOR</span>
                            <span className="text-brand-navy block text-sm font-semibold">Shuttle Reservation</span>
                        </span>
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        <p className="text-muted-foreground text-sm text-balance">{description}</p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
