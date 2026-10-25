# Domain Research: How Nigerian Universities & Polytechnics Operate

Deep research into the academic and financial operations this system models, conducted
2026-10-01 with sources cited inline. Full reports:

| Report | Covers |
|---|---|
| [University academics (NUC)](research/university-academics.md) | 5-point grading, GPA/CGPA math, degree classes, carryovers, probation/withdrawal, CCMAS structure, student lifecycle (JAMB → NYSC) |
| [Polytechnic academics (NBTE)](research/polytechnic-academics.md) | ND/HND structure, the 4-point/9-band scale, Distinction–Pass classification, SIWES, probation rules, schools vs faculties |
| [Fees, payments & portals](research/fees-payments-portals.md) | Fee anatomy (acceptance, sessional, indigene pricing), Remita/TSA/RRR, Paystack/Flutterwave, installments, portal features, result approval workflow, competitor software |

## The ten facts that shaped the data model

1. **Two grading engines, one abstraction.** Universities grade on the NUC 5-point scale
   (A 70–100 = 5 … E 40–44 = 1, F = 0); polytechnics on the NBTE 4-point scale with nine bands
   and fractional points (A 75–100 = 4.00, AB = 3.50, B = 3.25, BC = 3.00, C = 2.75, CD = 2.50,
   D = 2.25, E = 2.00, F = 0). Both are **data** (`grade_scales` + `grade_scale_bands`), never code.
2. **GPA = TCP ÷ TNU**, where TNU counts every *registered* unit — a failed course's units stay
   in the divisor. CGPA recomputes from running totals, never by averaging GPAs.
3. **Degree classes differ**: First Class 4.50–5.00 … Pass 1.00–1.49 (note the authentic NUC 2:2
   lower bound of **2.40**, not 2.50) vs Distinction 3.50–4.00 … Pass 2.00–2.49.
4. **Carryovers register first.** A failed course must be re-registered, before new courses, the
   next time it is offered, within the unit cap (university 15–24; polytechnic ~18–24).
5. **Probation thresholds differ**: universities commonly CGPA < 1.50 (NUC baseline 1.00);
   polytechnics CGPA < 2.00 with **withdrawal after two consecutive semesters** below it.
6. **Results travel an approval chain**: Lecturer → Departmental Board/Exam officer → Faculty or
   School Board → **Senate** (university) / **Academic Board** (polytechnic). Students see only
   final-approved results; amendments need a formal flow.
7. **Payment gates everything.** Fees must be paid (fully, or to a configured installment
   percentage) before course registration; exam cards require full clearance. Federal
   institutions must collect via **Remita/TSA with RRR references**; others use Paystack,
   Flutterwave, Interswitch, Monnify, or bank tellers — hence a gateway-agnostic payment layer.
8. **Fees are itemized bundles** varying by session × programme × level × fresh/returning ×
   indigene status — modelled as `fee_types` + rule-based `fee_structures` resolved
   most-specific-wins into per-student invoices.
9. **ND and HND are separate programmes** with separate admissions, separate CGPAs and usually
   separate matric numbers, bridged by a mandatory post-ND industrial training year. Polytechnics
   group departments under **Schools**, universities under **Faculties** (configurable label).
10. **Matric numbers have no national format** — each institution composes its own from
    school/faculty/department/programme/year/serial tokens, so generation is template-driven.
