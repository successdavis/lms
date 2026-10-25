import { Row, VerifyShell } from '@/components/portal/verify-shell';

interface Props {
    institution: string | null;
    student: {
        name: string;
        matric_no: string | null;
        programme: string;
        department: string;
        faculty: string;
        status: string;
        valid_until: string;
        graduated_on: string | null;
    };
    photoUrl: string | null;
    checkedAt: string;
}

export default function VerifyStudent({ institution, student, photoUrl, checkedAt }: Props) {
    const current = ['active', 'probation', 'spillover', 'graduated'].includes(student.status);

    return (
        <VerifyShell title="Student ID verification" institution={institution} verified={current} checkedAt={checkedAt}>
            {photoUrl && <img src={photoUrl} alt="Passport" className="mx-auto mb-3 h-32 w-28 rounded border object-cover" />}
            <Row label="Name" value={student.name} />
            <Row label="Matric No" value={student.matric_no} />
            <Row label="Programme" value={student.programme} />
            <Row label="Department" value={student.department} />
            <Row label="Faculty / School" value={student.faculty} />
            <Row label="Status" value={<span className="capitalize">{student.status}</span>} />
            {student.graduated_on ? (
                <Row label="Graduated" value={student.graduated_on} />
            ) : (
                <Row label="Card valid until" value={student.valid_until} />
            )}
        </VerifyShell>
    );
}
