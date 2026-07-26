import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-white p-1 shadow-sm ring-1 ring-white/20">
                <AppLogoIcon className="size-full object-contain" />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="text-sidebar-foreground truncate text-[0.7rem] font-semibold tracking-[0.18em]">PAGCOR</span>
                <span className="text-sidebar-foreground/70 truncate text-xs font-medium">Shuttle Reservation</span>
            </div>
        </>
    );
}
