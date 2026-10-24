import { Row, VerifyShell } from '@/components/portal/verify-shell';

interface Props {
    institution: string | null;
    transcript: { number: string; status: string; issued: boolean; recipient: string; processed_at: string | null; dispatched_at: string | null };
    student: {
        name: string;
        matric_no: string | null;
        programme: string;
        graduated_on: string | null;
        cgpa: string | null;
        classification: string | null;
    };
    checkedAt: string;
}

export default function VerifyTranscript({ institution, transcript, student, checkedAt }: Props) {
    return (
        <VerifyShell title="Transcript verification" institution={institution} verified={transcript.issued} checkedAt={checkedAt}>
            <Row label="Transcript No" value={transcript.number} />
            <Row label="Issued to" value={transcript.recipient} />
            <Row label="Issued on" value={transcript.dispatched_at ?? transcript.processed_at} />
            <Row label="Student" value={student.name} />
            <Row label="Matric No" value={student.matric_no} />
            <Row label="Programme" value={student.programme} />
            {transcript.issued && <Row label="CGPA" value={student.cgpa} />}
            {transcript.issued && student.classification && <Row label="Class" value={student.classification} />}
            {student.graduated_on && <Row label="Graduated" value={student.graduated_on} />}
            {!transcript.issued && <p className="text-destructive pt-2 text-sm">This transcript number has not been issued by the institution.</p>}
        </VerifyShell>
    );
}
