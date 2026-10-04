# Nigerian Tertiary Institution Management System

A management system for Nigerian **universities** and **polytechnics**: students, staff,
academics (courses, registration, results, GPA/CGPA, carryovers) and finance (school fees,
invoices, payments). One codebase serves both institution types — everything that differs
between the NUC (university) and NBTE (polytechnic) systems is configuration, not code.

Built with **Laravel 12**, **React 19** (TypeScript) and **Inertia.js**.

## What's implemented (Phase 1 scaffold)

- **Academic structure** — faculties/schools, departments, programmes (B.Sc … ND/HND), levels,
  academic sessions & semesters, courses with credit units and prerequisites, per-programme
  curricula.
- **Grading engine** — DB-configurable grade scales seeded with the NUC 5-point scale and the
  NBTE 4-point (9-band) scale, plus degree/diploma classification bands.
- **Results pipeline** — CA + exam scores → grade snapshot, approval statuses
  (lecturer → HOD → faculty → senate), per-semester GPA/CGPA snapshots, probation and
  withdrawal detection (including the NBTE two-consecutive-semesters rule).
- **Course registration** — registration windows, fee gating, carryover-first enforcement,
  min/max unit loads, prerequisite checks.
- **Finance** — fee types & rule-based fee structures (session/programme/level/entry-mode/
  indigene filters), invoice generation (most-specific rule wins), gateway-agnostic payments
  (Paystack, Remita/RRR, Flutterwave, Interswitch, bank).
- **People & access** — student and staff registries, course allocations, roles via
  spatie/laravel-permission (registrar, bursar, dean, HOD, exam officer, lecturer, student…).
- **Seed data** — national grade scales, levels, roles, and a demo university with a
  curriculum, fee schedule and demo accounts.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the data model,
[`docs/RESEARCH.md`](docs/RESEARCH.md) for the domain research this is built on, and
[`docs/ROADMAP.md`](docs/ROADMAP.md) for what comes next (portals, admissions, transcripts,
hostels, NYSC/SIWES).

## Getting started

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # uses SQLite by default
composer run dev             # serves Laravel + Vite together
```

Demo accounts (password: `password`):

| Role | Email |
|---|---|
| Super admin | `admin@demo.edu.ng` |
| Lecturer | `lecturer@demo.edu.ng` |
| Student | `student@demo.edu.ng` |

## Tests

Domain rules are covered by Pest feature tests (grading bands, the NUC worked GPA example,
CGPA accumulation, classification boundaries, probation/withdrawal, registration gating and
carryover enforcement):

```bash
php artisan test
```
