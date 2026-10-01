# Roadmap

## Phase 1 — Foundation (this scaffold)
- [x] Laravel 12 + React 19 (Inertia, TypeScript) base with auth
- [x] Institution settings (university vs polytechnic mode)
- [x] Academic structure: faculties/schools, departments, programmes, levels, sessions, semesters
- [x] Courses, curriculum mapping (programme-courses), prerequisites
- [x] Students & staff registries, course allocations
- [x] Configurable grade scales (NUC 5-point, NBTE 4-point) + classification bands
- [x] Course registration with carryover-first and unit-load rules
- [x] Results pipeline (CA + exam → grade) with approval statuses
- [x] GPA/CGPA computation service + semester snapshots, probation detection
- [x] Fees: fee types, fee structures, invoices, payments (gateway-agnostic)
- [x] Roles & permissions, seeders with realistic Nigerian demo data

## Phase 2 — Portals
- [ ] Student portal: dashboard, course registration UI, results/transcript view, fee payment
      (Paystack/Remita/Flutterwave checkout + webhooks), printable course form & exam card
- [ ] Lecturer portal: allocated courses, score sheet upload (CSV + inline grid), result submission
- [ ] HOD/Dean: result approval queues, departmental analytics
- [ ] Bursary: fee structure management, payment reconciliation, revenue reports
- [ ] Registrar: student records, matric number generation, status changes

## Phase 3 — Admissions
- [ ] Applicant portal (separate guard): application forms, post-UTME screening scores
- [ ] Admission lists (merit, catch-up/supplementary), acceptance fee flow
- [ ] Conversion of admitted applicants to students (matriculation)

## Phase 4 — Extended administration
- [ ] Transcript generation (PDF) & dispatch tracking
- [ ] Hostel management (halls, rooms, bed spaces, booking + payment)
- [ ] Final-year clearance workflows; NYSC mobilization list (universities) /
      SIWES-IT placement & logbooks (polytechnics)
- [ ] Staff HR: promotions, leave, payroll hooks
- [ ] Notifications (email/SMS), audit log, reporting dashboards
