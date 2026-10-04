import { Head, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface Institution {
    name: string;
    short_name?: string | null;
    motto?: string | null;
    address?: string | null;
}

/**
 * Standalone print-friendly page: institution letterhead, a toolbar that
 * disappears when printing, black-on-white body.
 */
export function PrintLayout({
    title,
    documentTitle,
    institution,
    children,
}: {
    title: string;
    documentTitle: string;
    institution: Institution | null;
    children: ReactNode;
}) {
    return (
        <div className="min-h-screen bg-white text-black">
            <Head title={title} />

            <div className="mx-auto max-w-3xl p-8 print:max-w-none print:p-0">
                <div className="mb-6 flex items-center justify-between gap-2 print:hidden">
                    <Link href="/dashboard" className="text-sm text-neutral-500 underline">
                        ← Back to portal
                    </Link>
                    <button onClick={() => window.print()} className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium">
                        Print
                    </button>
                </div>

                <header className="border-b-2 border-black pb-4 text-center">
                    <h1 className="text-2xl font-bold uppercase">{institution?.name ?? 'Institution'}</h1>
                    {institution?.motto && <p className="text-sm italic">{institution.motto}</p>}
                    {institution?.address && <p className="text-xs">{institution.address}</p>}
                    <h2 className="mt-3 text-lg font-semibold uppercase underline">{documentTitle}</h2>
                </header>

                <main className="mt-6">{children}</main>
            </div>
        </div>
    );
}

export function BioGrid({ student, extra }: { student: Record<string, string | null>; extra?: [string, string][] }) {
    const rows: [string, string | null][] = [
        ['Name', student.name],
        ['Matric No', student.matric_no],
        ['Faculty/School', student.faculty],
        ['Department', student.department],
        ['Programme', student.programme],
        ['Level', student.level],
        ...(extra ?? []),
    ];

    return (
        <dl className="grid grid-cols-2 gap-x-8 gap-y-1 text-sm">
            {rows.map(([label, value]) => (
                <div key={label} className="flex gap-2 border-b border-dotted border-neutral-400 py-1">
                    <dt className="w-32 shrink-0 font-semibold">{label}:</dt>
                    <dd>{value ?? '—'}</dd>
                </div>
            ))}
        </dl>
    );
}
