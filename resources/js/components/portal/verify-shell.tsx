import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';

/**
 * Standalone public page (no portal chrome) for QR verification results.
 */
export function VerifyShell({
    title,
    institution,
    verified,
    children,
    checkedAt,
}: {
    title: string;
    institution: string | null;
    verified: boolean;
    children: ReactNode;
    checkedAt: string;
}) {
    return (
        <div className="bg-background text-foreground flex min-h-screen items-start justify-center px-4 py-10">
            <Head title={title} />
            <div className="w-full max-w-md rounded-xl border p-6 shadow-sm">
                <div className="text-muted-foreground text-center text-xs tracking-widest uppercase">{institution}</div>
                <h1 className="mt-1 text-center text-lg font-semibold">{title}</h1>
                <div
                    className={`mx-auto mt-3 w-fit rounded-full px-3 py-1 text-sm font-medium ${verified ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'}`}
                >
                    {verified ? 'Genuine record' : 'Record not valid'}
                </div>
                <div className="mt-5 space-y-2 text-sm">{children}</div>
                <p className="text-muted-foreground mt-6 text-center text-xs">Checked against live records on {checkedAt}.</p>
            </div>
        </div>
    );
}

export function Row({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 border-b border-dashed pb-1">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value ?? '—'}</span>
        </div>
    );
}
