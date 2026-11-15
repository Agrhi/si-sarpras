import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-muted p-6 md:p-10">
            <div className="w-full max-w-md">
                <Card className="gap-2 rounded-xl">
                    <CardHeader className="px-10 pt-8 pb-0 text-center">
                        <Link
                            href={home()}
                            className="mx-auto mb-2 flex size-24 items-center justify-center"
                        >
                            <AppLogoIcon className="size-24" />
                        </Link>
                        <CardTitle className="text-2xl font-bold">
                            {title}
                        </CardTitle>
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </CardHeader>
                    <CardContent className="px-10 pt-3 pb-8">
                        {children}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
