# Phase 9 re-audit - Payroll Review and Approval

Date: 2026-10-27 (Asia/Manila). Scope: current uncommitted Phase 9 sources after
the F1 correction, against `master-prompt.md:537`. Audit only: no application fix,
permanent-test change, migration, commit or Phase 10 implementation.

## Result

**Implemented but needs validation. No new confirmed Phase 9 defect in the
exercised supported paths. F1 remains corrected.** All seven task areas are
present, and all three acceptance criteria pass for the tested demonstration
workflows. This does not establish production payroll/client or live concurrency
acceptance.

| Acceptance criterion | Assessment | Evidence and limits |
| --- | --- | --- |
| Payroll Officer cannot approve their own submission | Passed for exercised application paths | Role/status checks plus the shared preparation-independence rule reject self-review, including after role changes. Creator, submitter, input/core/statutory authors and retained coverage editors are covered. The renderer hides forbidden forms; the write transaction rechecks the current locked actor/session/batch independently. Unknown overwritten legacy coverage-only editors remain unrecoverable. |
| Returned payroll can be corrected and resubmitted | Passed for supported demonstration corrections | Corrected loan values and refreshed coverage after an employee-office change can be recalculated, reviewed, resubmitted and finalized. Earlier inputs, submission revisions, correction reasons and totals remain available. Six-revision history/pagination and return-event rollback diagnostics pass. |
| Finalized payroll cannot be silently modified | Passed at application boundary | Preparation routes reject writes in submitted/approved/finalized states. Ordinary model mutations of immutable workflow/finalized records, including a stale batch instance, are rejected. Later employee/configuration changes leave finalized values intact. Atomic persistence and duplicate/stale request tests pass. Privileged SQL changes, simultaneous writer scheduling and failure recovery are outside this evidence. |

Present task areas: review screen, submission, Approver review, approval/return
with reason, returned correction, authorized finalization and snapshot locking.
No additional missing supported Phase 9 task area was confirmed.

## F1 correction review

- `app/Services/PayrollWorkflowService.php:125` exposes the existing preparation-
  independence determination without writes and rejects a submission from another
  batch. `:143` reuses it for the server denial; `:178` invokes that guard during
  the locked transition. There is no cached read-time permission used to authorize
  later decisions.
- `app/Http/Controllers/PayrollReviewController.php:22` computes independence once
  for role/status-eligible reviewers using the current batch/submission.
- `resources/views/payroll-reviews/show.blade.php:19` and `:23` require that result
  alongside the existing role/status policy before showing review/finalize forms.
- Permanent corrected-behavior tests pass for creator/submitter and isolated input,
  core, statutory and coverage authors after role changes, with positive independent
  Approver controls and successful finalization. Direct forbidden requests still
  return 403 and preserve snapshots/history.
- New audit diagnostics check all four roles across submitted/approved/finalized
  views. Independent Approvers retain actions; other roles do not acquire them.
  Viewing does not rewrite submissions/finalizations. Revoking an Approver's role
  or session after rendering still prevents approval inside the transaction.

No regression of F1 or new authorization/snapshot defect was confirmed. The original
audit's F1 reproducer intentionally asserts the former behavior; it was excluded
from current acceptance checks, not rewritten to erase historical evidence.

## Missing, blocked and deferred requirements

- Client-approved payroll rules, category applicability, attendance, statutory/tax
  cases, authority/evidence practices and minimum-net/refund policy remain pending.
  Real payroll stays blocked and finalized demonstrations retain
  `production_ready=false`.
- Prototype authority permits the same independent Approver to approve and
  finalize. Any required additional signatory or external-evidence verification
  needs client acceptance, rather than an invented workflow.
- Pre-migration coverage-only editors overwritten before tracking was added cannot
  be reconstructed. Known creator/last-editor identities, saved input/core/statutory
  authors and all new coverage editors are checked; a full legacy audit trail is
  not claimed.
- Negative-net and unexecuted credit/refund scenarios remain blocked for submission.
  Their amounts are not silently capped or disbursed.
- Finalized corrections, payroll documents and blockchain belong to later phases.
  They are not missing Phase 9 tasks and were not implemented in this re-audit.
- Earlier Phase 2/3 findings are unchanged and remain outside this current-phase
  audit's correction scope.

## Checks executed

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: 443 tests / 4768 assertions, 76.617 seconds, 74 MB; inherited production/MySQL/DB_URL settings remained isolated in-memory |
| New ignored re-audit diagnostics | Passed: 6 tests / 127 assertions; role/status controls, read preservation, revoked actor/session after rendering, helper batch binding and cross-batch decision rejection |
| Applicable prior ignored audit diagnostics | Passed: 9 tests / 140 assertions; stale submission, return rollback, finalizer revocation, history pagination, returned-source correction and isolated migration-method checks; old F1 reproducer excluded |
| MySQL workflow/configuration/core/statutory verifiers | All passed with owned fictional fixtures and rollback |
| `php artisan migrate:status` / `app:check` | Thirteen migrations already Ran; database/base tables ready; no migration executed |
| Application and workflow-verifier Pint `--test` | Passed |
| `git diff --check` | Passed |

MySQL was initially stopped, temporarily started for read/rollback-only checks and
stopped afterward. No fictional fixtures, rule activation, credential export,
operator configuration or payroll result was retained/changed by the audit.
No asset/dependency source changed; the asset build and Composer validation were
not rerun. Their latest passing evidence remains in TESTING_STATUS.md.

Reproduction commands, from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter=test_reaudit_ ../.local/phase9-reaudit/Phase9ReauditTest.php
php vendor/phpunit/phpunit/phpunit --colors=never --filter='/test_audit_(?!self_review_controls)/' ../.local/phase9-audit/Phase9AuditTest.php
```

These diagnostics are ignored and separate from the permanent suite. The legacy
migration-method probe runs only against isolated in-memory SQLite outside the
fixture's outer transaction; it does not verify populated transactional SQLite
migrations, MySQL downgrade or migration-interruption recovery.

## Coverage and remaining gaps

Permanent Phase 9 coverage remains **47 cases / 737 assertions**, alongside all
396 earlier cases. Fifteen additional applicable audit cases pass 267 assertions;
they do not increase the permanent count. No coverage percentage was measured.

Coverage includes roles/source authors, action markup and direct denials, current
actor/session checks, guest/CSRF, required reasons/confirmation, safe rejected input,
UTF-8/escaping, status/version/submission/token checks, incomplete/real blocking,
exact employee/batch arithmetic, corrected inputs/coverage and retained revisions,
history pagination, ordinary immutable-model guards, injected persistence rollback,
duplicate prevention and finalized retention after configuration activation.

Still unverified:

- Live simultaneous MySQL submit/approve/return/finalize operations and salary,
  employee or configuration publication races. Sequential stale requests are not
  evidence of real concurrency scheduling guarantees.
- Deadlock retry, connection loss and process interruption around commit.
- MySQL populated rollback/downgrade, partial-migration recovery and full legacy
  actor-history reconstruction.
- Live browser visual, keyboard, mobile/responsive and screen-reader interactions;
  HTTP-rendered markup checks do not establish browser/assistive acceptance.
- Office-scale employee/history volume, query/memory/response-time limits and
  client-approved expected payroll/authority examples.

Stop at Phase 9. No application/permanent-test change, new feature, Git commit or
Phase 10 work performed in this re-audit.
