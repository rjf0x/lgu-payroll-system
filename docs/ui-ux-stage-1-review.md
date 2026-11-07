# UI/UX Stage 1: Validation and Prototype Review

**Date:** November 7, 2026
**Status:** In progress; initial browser review and prototype delivered
**Related plan:** [UI/UX improvement plan](ui-ux-improvement-plan.md)
**Prototype:** [Shared shell, employee directory, and payroll review](ui-ux-stage-1-prototype.html)

## Method and boundaries

Reviewed the existing Blade screens, shared components, and four dashboard HTML prototypes. Walked through the running development application using the installed Microsoft Edge browser in an isolated headless profile. Used the existing demonstration accounts for Admin, Payroll Officer, Approver, and Viewer. Logins created normal authentication audit events. No payroll was computed, submitted, approved, rejected, or finalized during this review.

The new interaction prototype is a standalone HTML artifact with fictional records. It supports employee search/reset, screen navigation, a responsive menu, role previews, payroll decision dialogs, and local feedback. It does not connect to application data or enforce authorization. Its return/reject reason requirement is a proposed interaction for review, not a change to the backend workflow.

## Existing prototypes compared with the application

| Role | Existing prototype emphasis | Current application | Direction for the next stage |
|---|---|---|---|
| Admin | User access, organization, audit activity, and system settings | Live account/role summaries and management links; audit viewer/export added during phase 11; system settings remain pending | Prioritize access/configuration tasks and recorded activity. Keep audit filters aligned with the export. |
| Payroll Officer | Employee search, attendance, earnings, computations, and payroll register | Functional modules are separate screens; the payroll action area combines computation, submission, deletion, and report links | Preserve employee/period context and distinguish workflow actions from supporting documents. |
| Approver | Pending queue, totals, employee breakdown, and review decisions | Role dashboard filters pending runs and excludes the preparer/submitter; the run page exposes actions according to workflow eligibility | Present current stage, totals, and independent review guidance before decision controls. |
| Viewer | Read-only employee and finalized payroll information, reports, and verification previews | Dashboard lists finalized runs; links and routes follow actual permissions. The seeded Viewer has no `blockchain.verify` permission | Treat prototype navigation as illustrative. Preserve backend permissions and validate document availability per role before porting links. |

The older prototypes use compact labels and decorative symbol icons. Reuse their workflow ideas while applying readable type, consistent controls, accessible names, focus indicators, and text-based statuses. Do not copy demonstration actions as authorization rules.

## Browser findings

| Check | Result |
|---|---|
| Four demonstration logins | Each reached its expected role dashboard. |
| Admin audit access | Audit trail, event table, and filtered export link rendered. The link was absent from the other three role dashboards. Backend restrictions are also covered by feature tests. |
| Payroll preparation entry | Payroll Officer reached the New Payroll Run form and payroll-period selector. Form submission was intentionally outside this review. |
| Approver live review | No pending payroll run was available. A full live review walkthrough remains pending; the fictional prototype supplies a reviewable interaction sample. |
| Mixed-role behavior | Existing `DashboardTest` covers Admin plus Payroll Officer dashboard precedence and combined actions. No mixed-role development account was created for a browser walkthrough. |
| Browser telemetry | Boost browser-log lookup reported no browser log file. This is not evidence of a clean browser console. |

### Responsive employee-directory baseline

Measured document width and inspected whether a wide table was inside a container with hidden horizontal overflow. Scrollbar allocation can make document width slightly smaller than the emulated viewport.

| Viewport width | Current application | New prototype |
|---|---|---|
| 375 px | Document measured 382 px; employee table clipped; search input remained 288 px wide | No page overflow; employee table scrolls within its container |
| 768 px | Employee table clipped | No page overflow; employee table scrolls within its container |
| 1024 px | No clipped employee table detected | No page overflow |
| 1440 px | No clipped employee table detected | No page overflow |

These checks cover the employee directory. Review-screen and full-module responsive acceptance remains part of the implementation stages.

## Task baseline register

Participant task timing has not been collected. Automated navigation timing is not a substitute for human task time or usability errors.

| Task | Starting point and success condition | Evidence collected | Human time / task errors |
|---|---|---|---|
| Find an employee | Dashboard; locate a specified employee and open the correct record | Directory reached and search submitted. A single automated search navigation took approximately 513 ms, including a fixed 500 ms observation delay; this number is diagnostic only. | Not measured |
| Prepare payroll | Payroll Officer dashboard; select the intended period, compute, inspect totals, and submit | Preparation form reached; computation/submission still require an isolated demo scenario | Not measured |
| Review submitted payroll | Approver dashboard; inspect totals, identify a discrepancy, and record an appropriate decision | No pending live run; prototype review and confirmation interactions checked | Not measured |

For the participant baseline, record elapsed time, completion, wrong navigation choices, validation failures, and assistance needed. Repeat equivalent tasks after implementation with comparable data. Use an isolated demonstration dataset for state-changing tasks.

## Prototype interaction checks

- Employee search for the fictional Alex record returned one result.
- Employee-directory layouts showed no page overflow at 375, 768, 1024, and 1440 pixels.
- Selecting Approver exposed review actions; the other single-role previews explained their access context.
- The decision dialog opened with focus in the remarks field.
- Escape dismissed the native dialog when the browser page was activated and the Escape key was sent with its platform key code.
- Confirming approval changed the fictional status to Approved and displayed a message stating that no application payroll data changed.
- A screenshot of the prototype review was inspected locally. Development employee/payroll screenshots are not included in this document.

The first automated Escape attempt omitted page activation/platform key information and did not dismiss the dialog. A corrected browser input check passed. This was a browser-driver input issue; no custom Escape handler was added to the native dialog.

## Decisions carried forward

1. Retain emerald branding and a shared Blade/Tailwind workspace. Prioritize readability and predictable workflows.
2. Use collapsible navigation groups, breadcrumbs, visible current location, and controls with adequate touch targets.
3. Separate payroll workflow controls from reports. Show stage and eligibility guidance near the next action.
4. Keep wide-table scrolling inside a focusable, named region. Make search layouts adapt to available width.
5. Preserve server-side permissions and separation of duties. A role preview or hidden button provides no security boundary.

## Remaining Stage 1 work

- Collect human task-time and error baselines for the three tasks above.
- Walk through a populated pending run and a mixed-role account in an isolated demonstration environment.
- Review the prototype with representative staff and record any changes before implementing the shared shell in Stage 2.
- Complete keyboard-only task walkthroughs, screen-reader checks, contrast measurements, and review-screen responsive checks. Current component-level checks do not establish full accessibility conformance.

## Phase 11 coordination

The initial phase 11 implementation adds a read-only audit metadata viewer with shared filters for CSV export. Exports use chunked reads, neutralize spreadsheet formulas, exclude before/after payloads and user agents, and record an export event. Existing `audit.view` permissions determine access; no new permission seeding is required.

The authorization review found that account deactivation was enforced at login but did not invalidate an existing session on subsequent protected requests. An active-account middleware now logs such a session out, invalidates its data, regenerates its CSRF token, and redirects to login or returns a JSON denial. Feature tests cover blocked reads, writes, and session reuse.

Phase 11 remains in progress. System settings, the broader validation/authorization review, and the remaining database-constraint review are pending. Existing audit indexes cover record identity and module/action, and attendance already has an employee/date uniqueness constraint; no schema changes were made in this initial slice.
