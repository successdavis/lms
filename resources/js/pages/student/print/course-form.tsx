import { BioGrid, PrintLayout } from '@/components/portal/print-layout';

interface Props {
    institution: { name: string; motto?: string; address?: string } | null;
    student: Record<string, string | null>;
    session: string;
    semester: string;
    registration: {
        status: string;
        total_units: number;
        courses: { code: string; title: string; credit_units: number; is_carryover: boolean }[];
    };
}

export default function CourseForm({ institution, student, session, semester, registration }: Props) {
    return (
        <PrintLayout title="Course Registration Form" documentTitle="Course Registration Form" institution={institution}>
            <BioGrid
                student={student}
                extra={[
                    ['Session', session],
                    ['Semester', semester],
                ]}
            />

            <table className="mt-6 w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th className="border border-black px-2 py-1 text-left">S/N</th>
                        <th className="border border-black px-2 py-1 text-left">Course Code</th>
                        <th className="border border-black px-2 py-1 text-left">Course Title</th>
                        <th className="border border-black px-2 py-1 text-right">Units</th>
                        <th className="border border-black px-2 py-1 text-left">Remark</th>
                    </tr>
                </thead>
                <tbody>
                    {registration.courses.map((course, i) => (
                        <tr key={course.code}>
                            <td className="border border-black px-2 py-1">{i + 1}</td>
                            <td className="border border-black px-2 py-1 font-medium">{course.code}</td>
                            <td className="border border-black px-2 py-1">{course.title}</td>
                            <td className="border border-black px-2 py-1 text-right">{course.credit_units}</td>
                            <td className="border border-black px-2 py-1">{course.is_carryover ? 'Carryover' : ''}</td>
                        </tr>
                    ))}
                    <tr>
                        <td colSpan={3} className="border border-black px-2 py-1 text-right font-semibold">
                            Total Units
                        </td>
                        <td className="border border-black px-2 py-1 text-right font-semibold">{registration.total_units}</td>
                        <td className="border border-black px-2 py-1 capitalize">{registration.status}</td>
                    </tr>
                </tbody>
            </table>

            <div className="mt-16 grid grid-cols-3 gap-8 text-center text-sm">
                {['Student', 'Course Adviser', 'Head of Department'].map((signer) => (
                    <div key={signer}>
                        <div className="border-t border-black pt-1">{signer}</div>
                        <div className="text-xs text-neutral-600">Signature & Date</div>
                    </div>
                ))}
            </div>
        </PrintLayout>
    );
}
