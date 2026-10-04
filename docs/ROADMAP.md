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
- [x] Bursary: fee type/rule management UI, payment reconciliation (bank-teller recording,
      pending confirmation), revenue reports per session
- [x] Printable documents: course registration form, exam card (gated on full payment +
      registration), payment receipts
- [x] Score-sheet CSV upload with per-row validation
- [x] Paystack & Flutterwave signed webhooks; Remita (RRR) and Flutterwave drivers

## Phase 3 — Admissions
- [x] Admission cycles per session: application window, screening weights (UTME/Post-UTME,
      default 60/40), default aggregate cutoff
- [x] Applicant portal: self-service application form (biodata, programme choice, JAMB
      details), submit/lock, status tracker, offer acceptance, printable admission letter
- [x] Admissions office: applicant list with filters, inline post-UTME score entry, one-click
      screening (aggregate computation), admission lists (merit/supplementary) with
      cutoff-based auto-admit and manual admit (cutoff override), list publishing
- [x] Matriculation: accepted applicants converted to students (matric number generated,
      role swapped applicant → student); acceptance fee lands on the first session invoice,
      which gates course registration
- [ ] Application fee payment before submission; JAMB CAPS status fields; O'level
      document upload & verification checklist

## Phase 4 — Extended administration
- [ ] Transcript generation (PDF) & dispatch tracking
- [ ] Hostel management (halls, rooms, bed spaces, booking + payment)
- [ ] Final-year clearance workflows; NYSC mobilization list (universities) /
      SIWES-IT placement & logbooks (polytechnics)
- [ ] Staff HR: promotions, leave, payroll hooks
- [ ] Notifications (email/SMS), audit log, reporting dashboards
