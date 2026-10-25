# Research: Nigerian Polytechnic Academic Records (NBTE System)

Compiled 2026-10-01 from web sources (cited inline).

## 1. Programme Structure: ND and HND under NBTE

Polytechnics are regulated by the **National Board for Technical Education (NBTE)**, Kaduna —
the polytechnic counterpart of the NUC.

**National Diploma (ND)** — 2 years:
- **4 semesters** (levels **ND I** and **ND II**); each semester is **17 weeks**: 15 contact
  weeks + 2 weeks for exams/registration.
- Plus a **3–4 month SIWES** attachment, normally during the long vacation after ND I second
  semester (results folded into a "third semester" result at some institutions).
- Award requires **72–80 semester credit units** (programme-dependent), all prescribed
  coursework, the diploma project, and SIWES.

**Mandatory 1-year Industrial Training (IT) between ND and HND:**
- ND and HND are **separate programmes with separate admissions**. After ND, a minimum of
  **one year post-ND cognate industrial experience** is required for HND admission; ND **Pass**
  holders (CGPA 2.00–2.49) need **two or more years**.
- Software implication: support a gap/IT year between ND completion and HND enrolment, verified
  at HND admission (employer letter/IT logbook), not graded as a course.

**Higher National Diploma (HND)** — 2 years (**HND I**, **HND II**), same semester structure;
SIWES after HND I is not compulsory; HND II has a compulsory final-year project.

Sources: [NBTE curricula](https://www.digitalnbte.nbte.gov.ng/),
[NBTE Reviewed Entry Requirements for HND](https://www.nbte.gov.ng/nbte/sites/default/files/2024-01/Reviewed%20Entry%20Requirement.pdf),
[NBTE Accreditation Standards](https://www.nbte.gov.ng/nbte/sites/default/files/2024-01/STANDARDS%20FOR%20THE%20ACCREDITATION%20OF%20DIPLOMA%20PROGRAMMES%20IN%20POLYTECHNICS%20AND%20SIMILAR%20POST-SECONDARY%20TECHNICAL%20INSTITUTIONS%20IN%20NIGERIA.pdf)

## 2. The Polytechnic 4-Point Grading Scale (current NBTE unified scale)

Introduced as a **standardized unified grading system in the 2015/2016 session** (implemented
for ND I and HND I from May 2016). **Pass mark is 40%.**

| Grade | Score band (%) | Grade Point | Interpretation |
|-------|----------------|-------------|----------------|
| A  | 75 – 100 | 4.00 | Excellent |
| AB | 70 – 74  | 3.50 | Very Good |
| B  | 65 – 69  | 3.25 | Good |
| BC | 60 – 64  | 3.00 | Above Average |
| C  | 55 – 59  | 2.75 | Average |
| CD | 50 – 54  | 2.50 | Fair |
| D  | 45 – 49  | 2.25 | Pass |
| E  | 40 – 44  | 2.00 | Bare Pass (minimum pass) |
| F  | 0 – 39   | 0.00 | Fail |

- Federal Poly Ede's handbook: "The Polytechnic operates the 4-point grading system... Minimum
  score for Letter Grade E (Pass Mark) is 40%, while minimum score for Letter Grade A
  (Excellent) is 75%."
- **9 grade bands** (vs the university's 6) with intermediate letters (AB, BC, CD) and
  fractional points — store grade-scale definitions as data; pre-2016 transcripts may carry
  legacy variants.

Sources: [openeducat polytechnic gradebook](https://openeducat.org/gradebook/nigeria/polytechnic/),
[Federal Poly Ede Student Handbook](https://federalpolyede.edu.ng/downloads/STUDENT_HAND_BOOK_2020_2021.pdf),
[Federal Poly Ilaro 4-point scale paper](https://fpihumanitiesjournal.federalpolyilaro.edu.ng/storage/article/JHM_R18_(4-Point-Grading-Scale)[1]_1702371502.pdf)

## 3. GPA/CGPA Computation and Diploma Classification

- Quality Points per course = Credit Units × Grade Point
- **GPA = Σ(CU × GP) / Σ(CU registered)** per semester
- **CGPA** is cumulative across the programme — and **ND CGPA and HND CGPA are computed
  separately** (HND starts a fresh CGPA).
- Failed (F) courses contribute 0 quality points but their units count in the divisor; when a
  carryover is passed, most polytechnics keep both attempts in the computation (configurable).

**Diploma classification (NBTE, identical for ND and HND):**

| Class | CGPA range |
|-------|-----------|
| Distinction | 3.50 – 4.00 |
| Upper Credit | 3.00 – 3.49 |
| Lower Credit | 2.50 – 2.99 |
| Pass | 2.00 – 2.49 |
| Fail (no award) | below 2.00 |

- Minimum CGPA for award of ND or HND = **2.00**.
- Lower Credit (≥2.50) is the normal minimum for ND→HND progression.

## 4. Carryover / Probation / Withdrawal Rules

NBTE sets minimums; each Academic Board publishes its own regulations → configurable policies
with these defaults:

**Carryover:**
- Score < 40% (F) → **carryover**: re-register and retake **when next offered**, alongside
  current-level courses. Modern practice is carryover, not resit (the old "reference/resit"
  system has been phased out at most polytechnics).
- Graduating students with carryovers register only the carryover units in a **spill-over**
  semester/session; **75% attendance** per registered course (Ede rule).
- Course drop: up to 2 courses, no later than 4 weeks before exams (Ede rule; configurable).

**Probation and withdrawal:**
- Academic warning each semester CGPA < **2.00**.
- CGPA < 2.00 → **probation** next semester; advised to register minimum load.
- **Two consecutive semesters below 2.00 → withdrawal** for poor academic performance (some
  institutions offer programme transfer instead). NBTE accreditation standards require at least
  one probation semester before withdrawal.

**Credit load:** typically **minimum 18, maximum 24 units** per semester (configurable).

Sources: [Federal Poly Ede handbook](https://federalpolyede.edu.ng/downloads/STUDENT_HAND_BOOK_2020_2021.pdf),
[openeducat carryover](https://openeducat.org/gradebook/nigeria/reattempt/),
[Blueprint: Kogi Poly withdraws 229 students](https://blueprint.ng/kogi-polytechnic-withdraws-229-students-over-poor-academic-performance/)

## 5. Course Structure and Assessment

- **Course codes:** 3-letter prefix + 3 digits, e.g. GNS 101, MTH 112, EEC 125. First digit
  encodes level by convention (1xx = ND I, 2xx = ND II; 3xx = HND I, 4xx = HND II) — store the
  level explicitly rather than parsing it.
- **NBTE curriculum tables carry per-course:** L (lecture hrs/wk), P (practical hrs/wk),
  CU (credit units), CH (contact hours/wk), Year/Semester.
- **Curriculum composition (ND):** General Studies ≤ 15% of contact hours; foundation courses
  ≤ 25%; professional/trade courses 60–70%; plus SIWES.
- **Assessment split:** standard NBTE weighting is **CA 40% : exam 60%** (variants: CA 30% +
  project 10% + exam 60%; seminar courses CA 30% + report 70%). Configurable per course, 40/60
  default for polytechnics.
- Most NBTE curriculum courses are compulsory; electives are few.
- **SIWES grading:** ~3 credit units and/or **pass/fail** (NBTE standards say SIWES "is graded
  on a failure or pass basis"; failing means repeating 4 months at the student's expense).
  Assessment: ITF logbook (~30–40%), written report (~30–40%), supervisor visit scores, ITF
  Form 8, attendance. Needs a distinct enrolment type with placement record (employer,
  supervisor, dates, scores).

Sources: [NBTE curriculum specs](https://www.digitalnbte.nbte.gov.ng/),
[ITF SIWES document](https://itf.gov.ng/pdf/SIWES%20Work.pdf)

## 6. Student Lifecycle

**ND admission:** JAMB UTME required (national minimum cut-off 100–120 recently; institutions
set higher). O'Level: 5 credits (WAEC/NECO/NABTEB) incl. English & Maths, max 2 sittings.
Post-UTME screening by the institution. Part-time ND usually admits without JAMB (support as an
entry mode).

**HND admission:** ND in a cognate discipline from an NBTE-accredited programme, minimum
**Lower Credit (CGPA ≥ 2.50)** plus ≥1 year post-ND experience (Pass: ≥2 years); same O'Level
requirements. Institutional application (verify per client whether it now flows through JAMB).

**Matric numbers:** no national standard; institution-defined templates encoding
school/department/programme/mode/year/serial, e.g. `F/ND/23/3210045`, `P/HND/22/xxxxx`.
A student may hold **two matric numbers over a lifetime** (ND, then a new one for HND —
HND students are re-admitted and re-matriculated).

**Organisational structure:** **Schools → Departments → Programmes (ND/HND, FT/PT)**.
Example — Yaba College of Technology: 8 schools, 34 departments, ~70 accredited programmes.

**Lifecycle states:** Applicant → Admitted (ND) → ND1 → (SIWES) → ND2 → ND graduate
(classified) → [IT year, outside institution] → HND Applicant → HND1 → HND2 → HND graduate.
Parallel states: probation, withdrawal, suspension, spill-over, deferment. Certificates:
Statement of Result first, then certificate and transcript; ND transcript is a prerequisite
for HND admission.

## 7. Key Differences from Universities (what the software must handle)

| Dimension | University (NUC) | Polytechnic (NBTE) |
|---|---|---|
| Regulator | NUC | NBTE |
| GPA scale | 5.00 (6 bands, A at 70+) | 4.00 (9 bands, A at 75+), pass 40 |
| Classification | First Class … Pass | Distinction / Upper Credit / Lower Credit / Pass |
| Programme shape | One continuous 4–6 year degree, one matric number | Two separate 2-year programmes (ND, HND), separate admissions, separate CGPAs, usually separate matric numbers, 1-yr IT between |
| Entry | JAMB (UTME/DE) for all levels | JAMB for ND; HND via ND class + experience |
| IT | SIWES 3–6 months inside degree (some courses) | SIWES ~4 months inside ND + full post-ND IT year |
| Org units | Faculties → Departments | **Schools** → Departments |
| Probation | CGPA < 1.00–1.50 typical | CGPA < 2.00; two consecutive semesters → withdrawal |
| Award | B.Sc./B.Eng. etc. | ND certificate, then HND certificate |

### Caveats to verify per client institution
- Whether F is replaced or averaged with the retake grade in CGPA.
- Whether any resit provision survives locally.
- Exact SIWES unit value and letter-graded vs pass/fail.
- Whether HND admission now flows through JAMB CAPS.
- Part-time programme calendars (often 2.5–3 years for the same curriculum).
