# Architecture & Domain Model

Unified management system for Nigerian **universities** and **polytechnics**: students, staff,
academics (courses, registration, results, GPA/CGPA, carryovers) and finance (fees, invoices,
payments). Built with **Laravel 12** (API + Inertia) and **React 19** (TypeScript, Inertia.js).

## 1. Design principles

1. **One codebase, two institution types.** A single `institutions` settings record declares the
   type (`university` or `polytechnic`). Everything that differs between the two is **data, not
   code**:
   - Grade scales and classification bands are DB-configurable (`grade_scales`,
     `grade_scale_bands`, `classification_bands`), seeded with the NUC 5-point scale and the
     NBTE 4-point scale.
   - Academic unit labels differ (university *Faculty* vs polytechnic *School*; both live in the
     `faculties` table with a configurable label).
   - Programme types differ (`BSC`, `BA`, `BENG`, … vs `ND`, `HND`) with their own durations,
     levels and admission flows.
2. **Session/semester scoping.** Nigerian institutions run academic *sessions* (e.g. `2025/2026`)
   of two semesters (some run a rain/harmattan naming). Registration, results, fees and
   promotions are all scoped to a `(session, semester)` pair.
3. **Immutable result snapshots.** Grading rules can change over time; a student's result stores
   the scores *and* the resolved grade letter/point at computation time. GPA/CGPA snapshots are
   persisted per semester (`semester_results`) so transcripts never drift.
4. **Approval workflows.** Results travel: Lecturer → HOD → Faculty/School board → Senate/Academic
   Board. Each result row carries a status; students only see senate-approved results.
5. **Payment-gated registration.** Course registration for a session is blocked until the
   student's school-fees invoice for that session is paid (full or the configured minimum
   instalment percentage).

## 2. Domain model

### Identity & access
| Entity | Notes |
|---|---|
| `users` | Login identity for everyone (students, staff, admins). |
| roles/permissions | `spatie/laravel-permission`. Roles: `super-admin`, `registrar`, `bursar`, `dean`, `hod`, `exam-officer`, `lecturer`, `admission-officer`, `student`. |

### Academic structure
| Entity | Notes |
|---|---|
| `institutions` | Singleton settings: name, type (`university`/`polytechnic`), motto, logo, current session/semester, matric number format, unit labels. |
| `faculties` | University faculties or polytechnic schools (label configurable). |
| `departments` | Belongs to faculty; has HOD (staff). |
| `programmes` | e.g. *B.Sc Computer Science*, *ND Accountancy*, *HND Electrical Engineering*. Carries `award` (BSC/BA/BENG/LLB/ND/HND…), duration in semesters, entry mode(s), max credit units per semester, grade scale to use. |
| `levels` | 100–600 for universities; ND1/ND2/HND1/HND2 for polytechnics (numeric `rank` + display label). |
| `academic_sessions` | `2025/2026`, start/end dates, `is_current`. |
| `semesters` | First/Second per session, registration window (open/close, late-close), `is_current`. |
| `courses` | Code (`CSC 201`), title, credit units, department that owns it, semester number offered, CA/exam split, `is_general` (GST/GNS). |
| `programme_courses` | Curriculum map: programme × level × semester → course, with type `core` / `required` / `elective`, min elective units. |
| `course_prerequisites` | Self-referencing pivot. |

### People
| Entity | Notes |
|---|---|
| `students` | Profile + matric no, JAMB reg no, entry mode (UTME/DE/HND), entry session, current programme & level, study status (`active`, `probation`, `withdrawn`, `suspended`, `graduated`, `spillover`), personal data (state/LGA of origin for indigene fee rules, guardian, etc.). |
| `staff` | Staff number, type (`academic`/`non_academic`), designation (Graduate Assistant → Professor; Assistant Lecturer → Chief Lecturer for polytechnics), department, employment dates. |
| `course_allocations` | Lecturer ↔ course per (session, semester); who may upload scores. |

### Registration & results
| Entity | Notes |
|---|---|
| `course_registrations` | One per student per (session, semester). Status: `draft` → `submitted` → `approved` (level adviser/HOD). Stores total units; validates min/max unit load and **carryovers-first** rule. |
| `registered_courses` | Line items; snapshot of credit units; `is_carryover` flag; links back to the failed attempt. |
| `results` | Per registered course: CA score, exam score, total, resolved grade letter, grade point, quality points; status `pending` → `hod_approved` → `faculty_approved` → `senate_approved`; `is_passed`. |
| `semester_results` | Snapshot per student per semester: TNU (total units registered), TCP (total quality points), GPA, cumulative TNU/TCP, **CGPA**, academic standing (`good`, `probation`, `withdrawal`), remarks. |
| `grade_scales` / `grade_scale_bands` | e.g. NUC: A 70–100 → 5 … F 0–39 → 0. NBTE: A 75–100 → 4.00, AB 70–74 → 3.50, B 65–69 → 3.25, BC 60–64 → 3.00, C 55–59 → 2.75, CD 50–54 → 2.50, D 45–49 → 2.25, E 40–44 → 2.00, F 0–39 → 0. |
| `classification_bands` | First Class 4.50–5.00 … Pass 1.00–1.49 (university); Distinction 3.50–4.00, Upper Credit 3.00–3.49, Lower Credit 2.50–2.99, Pass 2.00–2.49 (polytechnic). |

### Finance
| Entity | Notes |
|---|---|
| `fee_types` | Acceptance fee, school fees, departmental, hostel, ICT, late registration, transcript… each flagged `blocks_registration`. |
| `fee_structures` | Amount rules per (session, fee type) with optional filters: programme, level, entry mode, indigene/non-indigene. |
| `invoices` / `invoice_items` | Generated per student per session from matching fee structures; statuses `unpaid`, `part_paid`, `paid`. |
| `payments` | Gateway (`paystack`, `remita`, `flutterwave`, `bank`/manual), reference (incl. Remita RRR), amount, status, webhook payload, verification timestamps. |

## 3. Core business rules (encoded in services)

- **Grading** (`app/Services/Academics/GradingService`): score → band on the programme's grade
  scale; stores letter + point at the time of grading.
- **GPA/CGPA** (`ResultComputationService`):
  `GPA = Σ(unit × point) / Σ(unit)`; CGPA accumulates TNU/TCP across all semesters.
- **Carryover**: a failed course (grade F, or below the pass band) becomes a carryover; next time
  the course runs, it must be registered **before** new courses and counts inside the unit cap.
- **Unit load**: min/max per semester from the programme (defaults 15–24); carryovers included.
- **Probation/withdrawal**: CGPA below the configured threshold (university default 1.50;
  polytechnic default 2.00) → probation; a second consecutive semester below threshold →
  advised to withdraw (configurable).
- **Registration gating**: a semester registration opens only when the session's
  `blocks_registration` invoices are settled to the configured minimum percentage.

## 4. Application layout

```
app/
  Enums/                  InstitutionType, StudentStatus, ResultStatus, PaymentStatus, …
  Models/                 Eloquent models (above)
  Services/
    Academics/            GradingService, ResultComputationService, RegistrationService
    Finance/              InvoiceGenerator, PaymentVerifier (gateway drivers)
  Http/Controllers/       Thin controllers per module
database/
  migrations/             Ordered: structure → people → academics → finance
  seeders/                Grade scales, classification bands, roles, demo faculty data
resources/js/             React (Inertia) pages: admin, student portal, staff portal
docs/                     RESEARCH.md (domain research), this file, ROADMAP.md
```

## 5. Module roadmap

See `docs/ROADMAP.md`. Phase 1 (this scaffold): foundation schema, grading engine, seed data.
Subsequent phases: admissions (post-UTME screening, admission lists), hostel management,
transcripts (PDF), ID cards, NYSC/SIWES lists, messaging, analytics dashboards.
