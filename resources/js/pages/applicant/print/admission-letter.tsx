import { PrintLayout } from '@/components/portal/print-layout';

interface Props {
    institution: { name: string; short_name?: string; motto?: string; address?: string } | null;
    applicant: { name: string; application_no: string; jamb_reg_no: string | null };
    programme: { name: string; code: string; award: string };
    department: string;
    faculty: string;
    session: string;
    list: string | null;
    admitted_on: string | null;
    accepted: boolean;
}

export default function AdmissionLetter({ institution, applicant, programme, department, faculty, session, list, admitted_on, accepted }: Props) {
    return (
        <PrintLayout title="Admission Letter" documentTitle="Provisional Offer of Admission" institution={institution}>
            <div className="space-y-4 text-sm leading-6">
                <div className="flex justify-between">
                    <div>
                        <div className="font-semibold">{applicant.name}</div>
                        <div>
                            Application No: <span className="font-mono">{applicant.application_no}</span>
                        </div>
                        {applicant.jamb_reg_no && (
                            <div>
                                JAMB Reg No: <span className="font-mono">{applicant.jamb_reg_no}</span>
                            </div>
                        )}
                    </div>
                    <div className="text-right">{admitted_on && <div>Date: {admitted_on}</div>}</div>
                </div>

                <p>Dear {applicant.name},</p>

                <p className="font-semibold">PROVISIONAL OFFER OF ADMISSION — {session} ACADEMIC SESSION</p>

                <p>
                    I am pleased to inform you that you have been offered provisional admission into{' '}
                    <span className="font-semibold">{institution?.name ?? 'this institution'}</span> to study{' '}
                    <span className="font-semibold">
                        {programme.name} ({programme.award})
                    </span>{' '}
                    in the Department of {department}, {faculty}
                    {list ? `, under the ${list}` : ''}.
                </p>

                <p>
                    This offer is provisional and subject to: (a) verification of your credentials during physical clearance; (b) payment of the
                    acceptance fee and prescribed school fees; and (c) confirmation of your admission on the JAMB Central Admissions Processing System
                    (CAPS). Any false declaration will lead to the withdrawal of this offer.
                </p>

                <p>
                    {accepted
                        ? 'Our records show you have accepted this offer. Proceed with clearance and fee payment on the portal.'
                        : 'To accept this offer, log in to the application portal and click "Accept admission offer", then proceed with clearance and fee payment.'}
                </p>

                <p>Congratulations!</p>

                <div className="mt-16 grid grid-cols-2 gap-8 text-center">
                    <div />
                    <div>
                        <div className="border-t border-black pt-1 font-semibold">Registrar</div>
                        <div className="text-xs text-neutral-600">For: {institution?.name}</div>
                    </div>
                </div>
            </div>
        </PrintLayout>
    );
}
