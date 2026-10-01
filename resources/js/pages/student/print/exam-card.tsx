import { BioGrid, PrintLayout } from '@/components/portal/print-layout';

interface Props {
    institution: { name: string; motto?: string } | null;
    student: Record<string, string | null>;
    session: string;
    semester: string;
    courses: { code: string; title: string; credit_units: number }[];
}

export default function ExamCard({ institution, student, session, semester, courses }: Props) {
    return (
        <PrintLayout title="Examination Card" documentTitle="Examination Clearance Card" institution={institution}>
            <p className="mb-4 rounded border border-black p-2 text-center text-sm font-semibold">
                This card certifies that the student named below has been cleared (fees fully paid, courses duly registered) to sit the {semester}{' '}
                examinations, {session} session. Present it with a valid ID at every examination hall.
            </p>

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
                        <th className="border border-black px-2 py-1 text-left">Course</th>
                        <th className="border border-black px-2 py-1 text-left">Title</th>
                        <th className="border border-black px-2 py-1 text-right">Units</th>
                        <th className="border border-black px-2 py-1 text-left">Invigilator's Sign</th>
                    </tr>
                </thead>
                <tbody>
                    {courses.map((course, i) => (
                        <tr key={course.code}>
                            <td className="border border-black px-2 py-1">{i + 1}</td>
                            <td className="border border-black px-2 py-1 font-medium">{course.code}</td>
                            <td className="border border-black px-2 py-1">{course.title}</td>
                            <td className="border border-black px-2 py-1 text-right">{course.credit_units}</td>
                            <td className="border border-black px-2 py-1"></td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="mt-16 grid grid-cols-2 gap-8 text-center text-sm">
                <div>
                    <div className="border-t border-black pt-1">Student's Signature</div>
                </div>
                <div>
                    <div className="border-t border-black pt-1">Exams Officer</div>
                    <div className="text-xs text-neutral-600">Signature & Stamp</div>
                </div>
            </div>
        </PrintLayout>
    );
}
