import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';

export default function AuthCardLayout({
    children,
    title,
    description,
}: {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}) {
    return (
        <div className="bg-brand-sky relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-6 md:p-10">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(20,84,212,0.12),transparent_36rem)]" />
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href={route('home')}
                    className="relative z-10 flex items-center justify-center gap-3 self-center rounded-2xl bg-white px-5 py-3 shadow-lg"
                >
                    <AppLogoIcon className="size-12 object-contain" />
                    <span className="text-left">
                        <span className="text-brand-blue block text-xs font-semibold tracking-[0.2em]">PAGCOR</span>
                        <span className="text-brand-navy block text-sm font-semibold">Shuttle Reservation System</span>
                    </span>
                </Link>

                <div className="relative z-10 flex flex-col gap-6">
                    <Card className="shadow-brand-navy/10 rounded-3xl border-white/70 bg-white/95 shadow-2xl">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-xl">{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">{children}</CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
