import { FlashMessages } from '@/components/portal/flash-messages';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'ID Card', href: '/student/id-card' }];

interface Props {
    institution: { name: string } | null;
    student: { name: string; matric_no: string | null; programme: string; department: string; faculty: string; status: string };
    validUntil: string;
    qr: string;
    photoUrl: string | null;
    pdfUrl: string;
}

export default function IdCard({ institution, student, validUntil, qr, photoUrl, pdfUrl }: Props) {
    const [file, setFile] = useState<File | null>(null);
    const ready = photoUrl !== null && student.matric_no !== null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ID Card" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Student ID card</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Upload a recent passport photograph (JPG/PNG, at least 200×200 pixels, max 1&nbsp;MB, plain background). The QR code on
                            the back lets anyone verify your card against live records.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form
                            className="flex flex-wrap items-center gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (file)
                                    router.post(
                                        '/student/id-card/passport',
                                        { passport: file },
                                        { forceFormData: true, onSuccess: () => setFile(null) },
                                    );
                            }}
                        >
                            <input type="file" accept=".jpg,.jpeg,.png" className="text-sm" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
                            <Button type="submit" variant="outline" disabled={!file}>
                                {photoUrl ? 'Replace photograph' : 'Upload photograph'}
                            </Button>
                            {ready ? (
                                <a href={pdfUrl}>
                                    <Button type="button">Download ID card (PDF)</Button>
                                </a>
                            ) : (
                                <span className="text-muted-foreground text-sm">
                                    {student.matric_no
                                        ? 'Upload a photograph to print your card.'
                                        : 'Your card can be printed once you have a matric number.'}
                                </span>
                            )}
                        </form>

                        {/* Preview at roughly card proportions (85.6 × 54 mm). */}
                        <div className="flex flex-wrap gap-4">
                            <div className="w-[342px] overflow-hidden rounded-lg border bg-white text-black shadow-sm">
                                <div className="bg-[#0f3d2e] px-2 py-1 text-center text-white">
                                    <div className="text-[11px] font-bold uppercase">{institution?.name}</div>
                                    <div className="text-[8px] tracking-widest">STUDENT IDENTITY CARD</div>
                                </div>
                                <div className="flex gap-3 p-3">
                                    <div className="flex h-24 w-20 shrink-0 items-center justify-center border border-[#0f3d2e] bg-neutral-100 text-[9px] text-neutral-500">
                                        {photoUrl ? <img src={photoUrl} alt="Passport" className="h-full w-full object-cover" /> : 'No photo'}
                                    </div>
                                    <div className="text-[10px] leading-tight">
                                        <div className="text-[8px] text-neutral-500 uppercase">Name</div>
                                        <div className="font-bold">{student.name}</div>
                                        <div className="mt-1 text-[8px] text-neutral-500 uppercase">Matric No</div>
                                        <div className="text-sm font-bold">{student.matric_no ?? '—'}</div>
                                        <div className="mt-1 text-[8px] text-neutral-500 uppercase">Programme</div>
                                        <div className="font-bold">{student.programme}</div>
                                    </div>
                                </div>
                                <div className="px-3 pb-2 text-[9px]">
                                    Valid until <strong>{validUntil}</strong>
                                </div>
                            </div>
                            <div className="flex w-[342px] items-end justify-between rounded-lg border bg-white p-3 text-[9px] text-black shadow-sm">
                                <p className="max-w-[60%]">
                                    Property of {institution?.name}. Carry at all times on campus and present at every examination hall. Not
                                    transferable.
                                </p>
                                <img src={qr} alt="Verification QR" className="h-24 w-24" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
