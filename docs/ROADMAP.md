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
- [x] Student portal: dashboard, course registration UI (carryover-first, unit-load feedback),
      results view with GPA/CGPA/classification, invoices & fee payment (gateway abstraction:
      demo driver + Paystack driver; Remita/Flutterwave to follow)
- [x] Lecturer portal: allocated courses, inline score-sheet grid, locked after approval
- [x] HOD/Dean/Registrar: result approval queues (pending → HOD → faculty → senate) with GPA
      snapshotting on final approval
- [x] Registrar: student records with search
- [ ] Bursary: fee structure management UI, payment reconciliation, revenue reports
- [ ] Printable documents: course form, exam card, receipts (print views/PDF)
- [ ] Score-sheet CSV upload; Paystack webhooks; Remita (RRR) & Flutterwave drivers

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
