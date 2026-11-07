# UI/UX Improvement Plan

**Status:** Stage 1 in progress; browser review and interaction prototype delivered, participant baselines pending
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

- Implement shared components, accessible forms, navigation, responsive table containers, and status presentation.
- Define consistent typography, spacing, colors, focus states, and button hierarchy.
- Apply these patterns to the employee list and payroll run screen first.

### Stage 3: Improve daily workflows

- Refine payroll preparation and review, including stage guidance and action confirmations.
- Reorganize role dashboards around pending work and next actions.
- Extend employee profiles and preserve context across related record creation.

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
