import { Toaster as Sonner, type ToasterProps } from 'sonner';

const Toaster = (props: ToasterProps) => (
    <Sonner
        position="bottom-right"
        closeButton
        richColors
        toastOptions={{ duration: 4500 }}
        {...props}
    />
);

export { Toaster };
