import { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({ className, ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/skma-logo.png"
            alt="SKMA"
            className={className}
            {...props}
        />
    );
}
