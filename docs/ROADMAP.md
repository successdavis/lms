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
- [x] Application fee paid through the gateway layer before submission (payments can now
      belong to an applicant instead of a student/invoice)
- [x] JAMB CAPS status tracking per applicant (record-keeping; CAPS has no public API)
- [x] Credential uploads (O'level, JAMB slip, birth certificate, LGA ID) with an
      admissions-office verification checklist (verify/reject with notes), private storage
      and owner-only downloads

## Phase 4 — Extended administration
- [x] Transcripts: student request (local/international × e-copy/hard-copy fee schedule) →
      transcript invoice → records-office queue → PDF compiled from Senate-approved results
      (grading key, per-semester GPA/CGPA, classification) → dispatch with tracking reference
- [x] Hostel management: halls by gender, rooms/bed spaces, booking gated on school fees,
      48-hour unpaid reservations that lapse and free the bed, confirmation on payment,
      Student Affairs occupancy and allocation admin
- [x] Final-year clearance: department, faculty, library, bursary and student-affairs sign-off;
      HOD/Dean scoped to the student's own department/faculty; bursary blocked by outstanding
      fees, department by outstanding carryovers; reject-with-note and resubmit
- [x] Graduation list (cleared, no carryovers) and NYSC mobilization CSV per batch (degree/HND
      only; under-30 mobilized, 30+ flagged for exemption; each graduate stamped once)
- [x] SIWES placements: student registration (minimum duration), coordinator approval, grading
      after the attachment (logbook /40, report /40, visits /20, pass mark 40)
- [x] Staff leave: working-day calculation, annual entitlement, HOD/registry approval
- [ ] Staff HR: promotions, payroll hooks
- [ ] Notifications (email/SMS), audit log, reporting dashboards
- [ ] Student ID cards; PDF versions of the remaining printables
