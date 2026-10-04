import { BioGrid, PrintLayout } from '@/components/portal/print-layout';

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface Props {
    institution: { name: string; address?: string } | null;
    student: Record<string, string | null>;
    payment: {
        reference: string;
        rrr: string | null;
        amount: string;
        gateway: string;
        channel: string | null;
        paid_at: string | null;
    };
    invoice: {
        number: string;
        session: string;
        total: string;
        amount_paid: string;
        status: string;
        items: { description: string; amount: string }[];
    };
}

export default function Receipt({ institution, student, payment, invoice }: Props) {
    return (
        <PrintLayout title="Payment Receipt" documentTitle="Official Payment Receipt" institution={institution}>
            <BioGrid
                student={student}
                extra={[
                    ['Session', invoice.session],
                    ['Invoice No', invoice.number],
                ]}
            />

            <table className="mt-6 w-full border-collapse text-sm">
                <tbody>
                    <tr>
                        <td className="border border-black px-2 py-1 font-semibold">Receipt Reference</td>
                        <td className="border border-black px-2 py-1 font-mono">{payment.reference}</td>
                    </tr>
                    {payment.rrr && (
                        <tr>
                            <td className="border border-black px-2 py-1 font-semibold">Remita RRR</td>
                            <td className="border border-black px-2 py-1 font-mono">{payment.rrr}</td>
                        </tr>
                    )}
                    <tr>
                        <td className="border border-black px-2 py-1 font-semibold">Amount Paid</td>
                        <td className="border border-black px-2 py-1 font-semibold">{naira(payment.amount)}</td>
                    </tr>
                    <tr>
                        <td className="border border-black px-2 py-1 font-semibold">Payment Method</td>
                        <td className="border border-black px-2 py-1 capitalize">
                            {payment.gateway}
                            {payment.channel ? ` (${payment.channel})` : ''}
                        </td>
                    </tr>
                    <tr>
                        <td className="border border-black px-2 py-1 font-semibold">Date</td>
                        <td className="border border-black px-2 py-1">{payment.paid_at ?? '—'}</td>
                    </tr>
                </tbody>
            </table>

            <h3 className="mt-6 text-sm font-semibold">Invoice breakdown</h3>
            <table className="mt-1 w-full border-collapse text-sm">
                <tbody>
                    {invoice.items.map((item) => (
                        <tr key={item.description}>
                            <td className="border border-black px-2 py-1">{item.description}</td>
                            <td className="border border-black px-2 py-1 text-right">{naira(item.amount)}</td>
                        </tr>
                    ))}
                    <tr>
                        <td className="border border-black px-2 py-1 font-semibold">Invoice Total</td>
                        <td className="border border-black px-2 py-1 text-right font-semibold">{naira(invoice.total)}</td>
                    </tr>
                    <tr>
                        <td className="border border-black px-2 py-1 font-semibold">Total Paid to Date</td>
                        <td className="border border-black px-2 py-1 text-right font-semibold">
                            {naira(invoice.amount_paid)} <span className="uppercase">({invoice.status})</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p className="mt-8 text-center text-xs text-neutral-600">
                This receipt is computer-generated and valid without a signature. Verify any receipt against the bursary's payment records using the
                reference above.
            </p>
        </PrintLayout>
    );
}
