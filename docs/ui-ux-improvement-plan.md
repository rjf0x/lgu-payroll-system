# UI/UX Improvement Plan

**Status:** Stage 3 daily workflows implemented; Stage 1 participant baselines remain pending
**Date:** November 7, 2026
**Roles:** Admin, Payroll Officer, Approver, Viewer
**Design guidance:** ui-ux-pro-max

## Review scope and direction

This plan is based on source inspection of the Blade layouts, dashboard, navigation, employee screens, attendance, payroll run, reports, and shared components. Browser validation is the first implementation step; visual and interaction findings still require confirmation in the running application.

The recommended direction is a clear, accessible administrative workspace: retain the emerald identity, improve readability, and make each user's next task obvious. Build on the existing role-specific dashboards, permission-aware navigation, reusable form components, inline errors, and preserved form input.

The skill's accessible style guidance and targeted data-dense dashboard results support this direction. Its generated marketing-style single-column layout does not fit this application and should not be applied.

## Prioritized improvements

| Priority | Area | Planned improvements | Success criteria |
|---|---|---|---|
| 1 | Shared design system | Standardize buttons, page headers, cards, filters, alerts, and status badges. Use readable typography, consistent spacing, visible keyboard focus, and larger controls. | Shared components behave consistently; normal text meets 4.5:1 contrast; primary touch controls reach 44 by 44 pixels. |
| 1 | Navigation | Make sidebar groups collapsible, keep the current group open, add breadcrumbs, and distinguish Monthly Payroll from Pending Approvals. Improve mobile navigation. | Exactly one current navigation item is indicated; users can identify their location and return to the previous list. |
| 1 | Payroll workflow | Separate workflow actions from report links. Show the current stage, next permitted action, and reasons actions are unavailable. Add contextual confirmation for recompute, reject, and finalize. | Users understand what happens next and the consequences before committing an action; existing permission and separation-of-duties rules remain enforced. |
| 1 | Forms and feedback | Build on existing inline errors and preserved input. Add linked error summaries, accessible error associations, helper text, submission feedback, and duplicate-submit prevention. | Failed submissions retain entered values and move focus to a useful error summary. |
| 2 | Tables and search | Standardize filter bars, reset controls, result counts, sorting, pagination, and empty states. Keep horizontal scrolling inside wide tables; use sticky headers and employee columns where useful. | Employee and attendance tables remain usable on narrow screens; returning from a record preserves filters and page. |
| 2 | Role dashboards | Reduce the welcome banner's prominence. Prioritize preparation tasks for payroll officers, review queues for approvers, configuration tasks for admins, and finalized records for viewers. | Each role sees its most relevant work and next action near the top. |
| 2 | Employee workspace | Extend employee profiles with permission-aware sections for employment, compensation, earnings, deductions, attendance, and leave. Preserve employee context when adding related records. | Staff can maintain an employee's records without repeatedly finding the same person across modules. |
| 3 | Reports and verification | Replace raw JSON filters with readable summaries. Clarify report scope, payroll period, generation time, and export availability. Put verification results before technical hashes. | Users can identify the correct report and understand verification outcomes without interpreting internal data formats. |

## Source findings

- The shared status component only distinguishes `ACTIVE`; payroll stages otherwise share the same neutral styling.
- Payroll actions, review remarks, and report links occupy one crowded action area.
- Employee search uses a fixed-width field, and employee and attendance tables use containers that can clip wide content.
- Navigation labels and some payroll details use 10 to 12 pixel text.
- The employee profile currently connects personal details primarily to employment records.
- Reports history displays filter data as JSON.

## Delivery sequence

### Stage 1: Validate and prototype

**Progress:** Initial role walkthroughs and responsive checks completed in headless Microsoft Edge. See [Stage 1 review](ui-ux-stage-1-review.md) for findings, measurement limits, and remaining work. Open the [interaction prototype](ui-ux-stage-1-prototype.html) to review the proposed shell, employee search, and payroll decision flow.

- Walk through the running application for each role, including accounts with multiple roles.
- Compare the dashboard prototypes already in `docs/` with current behavior.
- Record baseline task time and errors for finding an employee, preparing payroll, and reviewing a submitted run.
- Prototype the shared shell, employee list, and payroll review screen.

### Stage 2: Establish foundations

**Progress:** Implemented on November 7, 2026. Shared layout, navigation, form controls, error feedback, buttons, alerts, cards, page headers, status badges, and scrollable tables are available. The employee directory and payroll run screen use the new foundation. Stage 1 participant research remains open; proceeding with Stage 2 was explicitly authorized.

- Implement shared components, accessible forms, navigation, responsive table containers, and status presentation.
- Define consistent typography, spacing, colors, focus states, and button hierarchy.
- Apply these patterns to the employee list and payroll run screen first.

#### Delivered foundation

- Retained emerald branding, with shared semantic colors, readable form text, consistent spacing, 44-pixel button/control heights, and visible focus indicators on light and dark surfaces.
- Replaced duplicate desktop/mobile navigation with one responsive native menu. Groups collapse, the current group opens, breadcrumbs identify page hierarchy, and Pending Approvals has its own current-link indication.
- Added field hints, linked inline errors, `aria-invalid`/`aria-describedby`, and a focused error summary after failed submissions. Existing server validation and preserved input remain in use.
- Added submission feedback and duplicate-submit prevention to the employee form and payroll action forms. Controls restore when returning through browser history.
- Added employee search/reset and result counts, responsive named table regions, scoped column headings, and descriptive row links. The payroll table uses a sticky employee column and readable monetary values.
- Grouped the payroll overview, totals, employee breakdown, actions, documents, and approval history using shared components. Existing backend workflow and permission rules remain in place; richer stage guidance and decision confirmations belong to Stage 3.

#### Verification

- All four existing demonstration accounts reached their role dashboards with one main navigation and one current link. The approver queue indicated only Pending Approvals.
- Employee directory and a populated finalized payroll run were checked in Microsoft Edge at 375, 768, 1024, and 1440 pixels. Neither screen overflowed the page; wide data stayed inside scrollable, keyboard-focusable table regions. Screenshots were inspected locally.
- Mobile navigation opened with Enter and closed with Escape, returning focus to its summary control.
- A real invalid employee-form submission displayed the error summary, focused it, and associated all invalid fields with their inline errors. The request failed validation and did not create an employee.
- Browser-only synthetic submit events verified duplicate-submit blocking, busy feedback, and control restoration without creating application records.
- Sampled contrast ratios: muted text on white 7.58:1, primary button text 7.68:1, and form-field boundaries on white 4.76:1. These samples do not constitute a full accessibility audit.
- The full PHPUnit suite passed with 117 tests and 515 assertions, including nine foundation scenarios. Frontend build and PHP formatting checks passed.

The shared components affect existing consumers, while full visual migration of other modules remains in Stage 4. Human task measurements, screen-reader review, and live pending-run/mixed-role browser walkthroughs remain open from Stage 1.

### Stage 3: Improve daily workflows

**Progress:** Implemented on November 7, 2026 using the shared Stage 2 components. Payroll guidance, confirmation dialogs, work-focused dashboards, and employee workspaces are available.

- Refine payroll preparation and review, including stage guidance and action confirmations.
- Reorganize role dashboards around pending work and next actions.
- Extend employee profiles and preserve context across related record creation.

#### Delivered workflows

- Payroll runs show the current preparation/review/approval/finalization stage, next-step guidance, and explanations for unavailable actions, including separation of duties. Returned/rejected runs surface the latest correction remarks.
- Recompute, submit, review decisions, delete, and finalize actions have contextual confirmations identifying the period, run, employee count, and net pay. The native dialog focuses Cancel, supports keyboard dismissal, and resubmits through the existing protected endpoint. A native browser-confirm fallback remains available when JavaScript is unavailable.
- Approval remarks are optional; return/reject reasons are now required. The server validates remarks as text up to 2,000 characters. Approve, reject, and return controls follow their individual permissions; backend permission checks and independent-review enforcement remain authoritative.
- Dashboards prioritize editable/correction runs, independent reviews, approved runs awaiting finalization, and pending attendance/leave work. Corrections take precedence over drafts, and review work excludes runs prepared or submitted by the reviewer. Admins see access/configuration actions first; viewers get a finalized-record entry point. Multiple roles retain their combined actions.
- Employee profiles now include employment, recent compensation, earnings, deductions, and permission-protected attendance and leave sections. Related data is scoped to that employee, recent lists are bounded, and historical/future effective dates remain visible.
- Employment, compensation, earning, deduction, attendance, and leave creation can start with the employee or employment selected. Save and Cancel return to the workspace; validation keeps the selected context. Updates on the supported related forms also preserve workspace navigation. Redirect destinations are built from named application routes and the saved record, rather than a supplied return URL.
- Employee-specific compensation/assignment lists show the active employee context and a reset option. Directory search/page parameters survive the list-to-profile-to-directory path.

#### Verification and limits

- The full suite passed with 149 PHPUnit tests, including 32 daily-workflow cases covering state guidance, independent review, permission-specific decisions, invalid remarks, dashboard priorities, employee data scoping, six prefilled creation forms, contextual saves, validation recovery, and safe return navigation.
- Microsoft Edge checks covered all four demo-role dashboards, employee section visibility, and employee/employment preselection. The dashboard, employee workspace, and populated finalized payroll screen fit at 375, 768, 1024, and 1440 pixels without page overflow. Screenshots were inspected locally.
- Browser-only confirmation checks used a temporary fictional form and intercepted the confirmed submit before navigation. Blank return/reject reasons were blocked, Cancel dismissed without submission, the dialog focused Cancel, and confirmation retained the selected submit action. No development payroll was changed by these checks.
- Frontend build and PHP formatting passed. No dependencies, payroll calculation rules, or database schema were changed.

The development database had finalized runs available for browser inspection; pending-run state changes and mixed-role behavior were exercised through isolated feature tests. Staff task-time measurements, a live pending-run/mixed-role walkthrough in an isolated demo environment, and screen-reader testing remain open from Stage 1. Stage 4 will migrate the remaining screens and improve reports and print layouts.

### Stage 4: Complete and verify

- Apply the shared patterns to remaining modules.
- Improve report discovery, readable metadata, verification outcomes, and print layouts.
- Conduct usability checks and compare results with the baseline.

## Acceptance and validation

- Complete key tasks using only the keyboard, with visible focus and predictable focus movement.
- Check layouts at viewport widths of 375, 768, 1024, and 1440 pixels. Keep necessary wide-table scrolling within its container.
- Verify accessible field labels, linked inline errors, error summaries, and announced submission feedback.
- Confirm filters, pagination, and employee context survive navigation where appropriate.
- Verify status meaning is conveyed through readable text as well as color.
- Run targeted regression tests for permissions, separation of duties, and payroll transitions when those interactions change.
- Compare task completion time and errors before and after the improvements.
- Verify report and payslip print layouts with realistic data.

## Recommended starting point

Start with the shared shell and payroll run screen. They offer the strongest combination of system-wide consistency and improvement to the central payroll workflow. Continue using the existing Blade and Tailwind structure; any dependency changes require separate approval.
