import { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({ alt = 'PAGCOR logo', ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/images/pagcor-logo.png" alt={alt} {...props} />;
}
