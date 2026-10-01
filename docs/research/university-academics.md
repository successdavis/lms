# Research: Nigerian University Academic Records (NUC System)

Compiled 2026-10-01 from web sources (cited inline). Each rule is marked **NUC mandate**
(standard across universities) or **institution-specific** (must be configurable).

## 1. The NUC Grading System (5-Point Scale)

Current standard (NUC-mandated, restored 2018/2019 session, retained in CCMAS 2022/2023):

| Score band (%) | Letter grade | Grade point |
|---|---|---|
| 70–100 | A | 5 |
| 60–69 | B | 4 |
| 50–59 | C | 3 |
| 45–49 | D | 2 |
| 40–44 | E | 1 |
| 0–39 | F | 0 |

- **Pass mark is 40% (grade E)** under the standard NUC scale. CCMAS 2022 documents retain the
  full 6-row table, including E = 40–44 = 1 point.
- **The "E" grade is NOT universally abolished**, but it is the single most variable point:
  NUC has *recommended* raising the pass mark to 45%, and some universities (especially newer
  private ones, and faculties like Law/Medicine) use **45% as pass mark**, collapsing to
  A/B/C/D with F = 0–44. → per-institution/per-programme grade-scale configuration required.
- **History (matters for transcripts):** in 2017 NUC introduced a 4-point scale that eliminated
  the Pass class; it caused classification distortions and in **October 2018 NUC ordered all
  universities to revert to the 5-point scale** effective 2018/2019. Transcripts for 2017/2018
  students may carry 4-point entries → keep a grade-scale reference per result record.
- Medicine/Law often use pass/fail or distinction/pass outside the GPA system.

Sources: [Vanguard – NUC orders reversion](https://www.vanguardngr.com/2018/10/nuc-orders-varsities-to-revert-to-5-point-grading-system/),
[EduCeleb](https://educeleb.com/nuc-universities-revert-5-point-grading/),
[NUC Engineering CCMAS PDF](https://www.nuc.edu.ng/wp-content/uploads/2022/12/Engineering-CCMAS.pdf),
[Academic grading in Nigeria](https://en.wikipedia.org/wiki/Academic_grading_in_Nigeria)

## 2. GPA / CGPA Computation (NUC-standard nationwide)

- **Credit Unit (CU)**: 1 CU = 1 hour of lecture/tutorial per week per semester (15 weeks), or
  3 hours of lab/practical per week. Courses carry 1–6 units (2–4 most common).
- **Quality Points (QP)** = Credit Units × Grade Point.
- **TNU** – total credit units for all courses **registered** (not just passed).
- **TCP** – total quality points over the same period.
- **GPA (semester)** = TCP ÷ TNU, to 2 decimal places.
- **CGPA** = cumulative TCP ÷ cumulative TNU across all semesters — NOT the average of
  semester GPAs; always recompute from running totals.

Worked example (one semester):

| Course | Units | Score | Grade | GP | QP |
|---|---|---|---|---|---|
| MTH101 | 3 | 78 | A | 5 | 15 |
| PHY101 | 3 | 64 | B | 4 | 12 |
| CHM101 | 3 | 71 | A | 5 | 15 |
| GST101 | 2 | 80 | A | 5 | 10 |
| GNS103 | 2 | 55 | C | 3 | 6 |
| **Totals** | **13 (TNU)** | | | | **58 (TCP)** |

GPA = 58 ÷ 13 = **4.46**. Next semester TNU=15, TCP=48 → CGPA = (58+48) ÷ (13+15) = **3.79**.

**Critical rule:** an F (0 points) still contributes its units to TNU, dragging CGPA down.
Truncate/round CGPA to 2 d.p. (most truncate; some round — configurable).

Sources: [Effikos](https://www.effikos.com/how-to-calculate-gpa-and-cgpa-in-a-nigerian-university/),
[openeducat](https://openeducat.org/articles/nigeria-university-grading-system-cgpa-explained/)

## 3. Degree Classification (NUC-mandated)

| Class of degree | CGPA range |
|---|---|
| First Class Honours | 4.50 – 5.00 |
| Second Class Honours (Upper Division) "2:1" | 3.50 – 4.49 |
| Second Class Honours (Lower Division) "2:2" | 2.40 – 3.49 |
| Third Class Honours | 1.50 – 2.39 |
| Pass | 1.00 – 1.49 |
| Fail (no award) | below 1.00 |

- The **2.40** lower bound of 2:2 (not 2.50) is the authentic NUC band — assuming 2.50 is a
  common implementation bug. (Some universities use 2.50–3.49 / 1.50–2.49; configurable, but
  default to NUC's 2.40.)
- NUC has recommended phasing out the "Pass" degree; most universities still award it.

Sources: [openeducat honours](https://openeducat.org/gradebook/nigeria/honours/),
[Academic grading in Nigeria](https://en.wikipedia.org/wiki/Academic_grading_in_Nigeria)

## 4. Carryover Rules

- A failed course (grade F / below pass mark) must be **re-registered and retaken** when next
  offered. Universities generally have **no resit exams** — the full course is repeated (CA +
  exam).
- **Priority registration (near-universal):** registration order is (1) carryovers first,
  (2) outstanding lower-level courses, (3) current-level courses — all within the max load.
  UNIZIK: the student "shall first enter the course(s) he/she was unable to pass in the
  previous year before registering courses for the current year."
- **Credit load (NUC standard): min 15, max 24 units/semester** (minimum waived in final/project
  semesters; some allow final-year students up to 30 with Senate approval).
- **Effect on CGPA — two institutional models (configurable):**
  1. **Both-attempts model (most federal universities):** the F stays permanently; both the
     failed and repeated registrations count in TNU.
  2. **Replacement model (some private universities):** the new grade replaces the F in CGPA;
     the original F stays on the transcript.
- **Prerequisites:** block registration of dependent courses; configurable "must pass" vs
  "must have attempted".
- Courses usually run only in their home semester (odd codes 1st semester, even 2nd), so a
  failed 1st-semester course is often retaken a full year later.
- **Graduation gate:** no outstanding carryover; all core courses passed; minimum total units.

Sources: [UNIZIK General & Academic Regulations](https://unizik.edu.ng/wp-content/uploads/2023/09/GENERALAND-ACADEMICREGULATIONS.pdf),
[openeducat carryover](https://openeducat.org/gradebook/nigeria/reattempt/),
[NOUN credit-unit policy](https://nou.edu.ng/wp-content/uploads/2023/08/BENCHMARK-Policy-on-Number-of-Credit-Unit-Per-Semester.pdf)

## 5. Probation, Withdrawal, Spill-over, Maximum Duration

- **NUC/BMAS baseline:** CGPA **below 1.00** at session end → **probation for one session**;
  still below 1.00 after the probation session → **required to withdraw** (often may transfer
  to another programme).
- **Institutional variation:** many universities place "good standing" at **CGPA ≥ 1.50**,
  probation below 1.50, withdrawal below 1.00 (or below 1.50 after probation). UNILAG works
  semester-wise. → thresholds and cadence (semester vs session) are institution config.
- Probation students are restricted to a **reduced credit load** (commonly max 15–18 units).
- **Spill-over students:** finish the final scheduled year with uncleared carryovers and
  register extra semesters; minimum-load rule waived; NYSC delayed.
- **Maximum duration (NUC standard): 1.5 × normal programme duration.** 4-year → 6 years max;
  5-year → 7.5/8; 6-year (medicine) → 9. Approved deferments usually don't count. Exceeding the
  maximum ⇒ compulsory withdrawal.
- Withdrawal types: academic failure, voluntary/deferment, disciplinary — distinct statuses.

Sources: [BUK GEAR](https://buk.edu.ng/sites/default/files/pdf/gear.pdf),
[UNILAG student guide](https://ice.unilag.edu.ng/resources/studentguide.pdf)

## 6. Academic Structure

- **Hierarchy:** University → Colleges/Faculties → Departments → Programmes → curriculum version
  (BMAS vs CCMAS) → level → semester → course.
- **Sessions & semesters:** "2024/2025 session", two semesters (First/Harmattan, Second/Rain) of
  ~15–17 weeks including exams.
- **Levels:** 100L–400L (4-year), 500L (engineering/law/pharmacy), 600L (medicine). **Direct
  Entry** enters at 200L (ND Distinction can enter 300L at some universities).
- **Course codes:** 3-letter prefix + 3 digits (CSC201). First digit = level; by convention the
  last digit is odd for first-semester, even for second-semester courses. GST/GNS/GES = General
  Studies.
- **Course types (BMAS/CCMAS):** Compulsory/Core (must pass), Required (must take; must-pass
  varies), Elective (replaceable), Optional (may be outside CGPA). GST and ENT entrepreneurship
  courses are NUC-compulsory for all undergraduates.
- **CCMAS note (Sept 2023 onward):** CCMAS replaced BMAS; NUC prescribes **70%** of content,
  universities design **30%**. Pre-2023 cohorts finish on BMAS → support multiple concurrent
  curriculum versions per programme.
- **Assessment split:** standard **CA 30% + exam 70%**; a sizeable minority use 40/60;
  lab/project courses can be 100% CA. Per-course setting with institution default. Many
  universities require ≥65–75% attendance to sit exams.
- **Results workflow (universal):** lecturer → Departmental Board of Examiners → Faculty Board →
  **Senate** final approval. Results are provisional until Senate approval; only Senate approves
  graduation lists. Final-year results moderated by external examiners. States: draft →
  department-approved → faculty-approved → senate-approved (immutable; corrections need a formal
  Senate amendment flow).

## 7. Student Lifecycle

1. **UTME (JAMB):** CBT exam, 4 subjects (English compulsory), scored 0–400. National minimum
   ~140; competitive courses 250+. O'Level: ≥5 credits incl. English & Maths, max 2 sittings.
2. **Post-UTME screening:** institution-run; **aggregate score** commonly JAMB 60% + Post-UTME
   40% (or JAMB 50% + O'Level points 50%) — configurable weights.
3. **Admission via JAMB CAPS:** candidate accepts on CAPS; JAMB issues the admission letter.
4. **Acceptance fee:** non-refundable (₦20,000–₦100,000+, institution-specific).
5. **Clearance & first registration:** credential screening, fees, **matric number issued**,
   biometrics, course registration, matriculation ceremony.
6. **Matric number formats vary** (configurable template): `ENG/2026/1234`, `2015/199550` (UNN),
   `170404001` (UNILAG), `U2019/5570089` (UNIPORT). JAMB reg number is a separate field.
7. **Each session:** fees → course registration within deadline (late penalty; add/drop window)
   → adviser/HOD-signed course forms → exams → results. "No registration, no exam."
8. **Graduation:** final clearance → Senate approves list → **Statement of Result** (used for
   NYSC) → certificate at convocation → **transcript** on paid request, sent directly to
   institutions (students never handle the sealed copy).
9. **NYSC:** institution uploads Senate-approved graduate list to the NYSC portal.

## Configurable vs fixed summary

**NUC-fixed (safe defaults):** 5-point grade values; class bands (4.50/3.50/2.40/1.50/1.00);
15–24 unit load; 1.5× max duration; two semesters/session; GPA = TCP/TNU; Senate workflow;
GST compulsory.

**Institution-configurable:** pass mark 40 vs 45 (E-grade existence); CA/exam split (30/70 vs
40/60, per course); carryover CGPA model (accumulation vs replacement); probation/withdrawal
thresholds (1.00 vs 1.50) and cadence; probation load cap; matric format; admission aggregate
formula; attendance threshold; 2:2 lower bound (2.40 vs 2.50); curriculum version per cohort.
