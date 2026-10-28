# Browser demonstration data

This explicit local/testing-only seeder creates the fictional November 2026
scenario from the browser walkthrough. It is a setup convenience for the current
prototype; it does not start Phase 13 or integrate Hyperledger Besu.

From the project root, start the existing local database if needed:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-mysql.ps1 -Action Start
cd application
php artisan migrate
php artisan db:seed --class=BrowserDemoSeeder
php artisan serve --host=127.0.0.1 --port=8000
```

Use built frontend assets (`npm.cmd run build` if not already built). Open
http://127.0.0.1:8000/login and leave the server terminal running.

## Accounts

| Username | Role |
| --- | --- |
| `demo.admin` | Administrator |
| `demo.payroll` | Payroll Officer |
| `demo.approver` | Approver |
| `demo.viewer` | Viewer |

New accounts use the public **local-demo password `LocalDemo2026!`**. These are
fictional development credentials, not existing user passwords. The seeder rejects
production/staging, including when Artisan is invoked with `--force`.

## Data created

| Number | Name | Classification | Position | Monthly salary |
| --- | --- | --- | --- | ---: |
| DEMO-P001 | Ana Santos | Permanent | Accounting Clerk | 30000.00 |
| DEMO-C001 | Ben Reyes | Coterminous | Budget Assistant | 30000.00 |
| DEMO-T001 | Carla Cruz | Part-time | Records Aide | 30000.00 |

All employees are active in Fictional Accounting Office, employed from
2024-01-01. Fresh demo setup uses one published **All departments** demonstration
schedule with grade 1 / step 1, effective 2026-11-01, shared by the three personnel
classifications. Previously seeded classification schedules remain preserved and
are reused on reruns. Initial fictional salary assignments use that same date and
a 2024-01-01 service-review basis. These are not official salary schedules.

The November 2026 demonstration draft covers all three employees. It has saved
empty earnings/loan/adjustment groups, zero absence/undertime, a current core
preview and a saved contribution/withholding reference review per employee.
Coverage for each component is explicitly fictional/applicable; bases are
30000.00, monthly regular taxable compensation 26350.00, other tax inputs zero.
Authority references/reasons are labeled FICTIONAL. No membership or entitlement
is established for actual personnel by these scenarios.

Using the shipped reference profile, expected totals are:

| Result | Per employee | Batch |
| --- | ---: | ---: |
| Gross earnings | 30000.00 | 90000.00 |
| GSIS | 2700.00 | 8100.00 |
| Pag-IBIG | 200.00 | 600.00 |
| PhilHealth | 750.00 | 2250.00 |
| Withholding | 827.55 | 2482.65 |
| Total deductions | 4477.55 | 13432.65 |
| Net pay | 25522.45 | 76567.35 |

Existing maintained payroll configurations are respected, not overwritten or
activated by the seeder. Different configured rules may change the resulting
amounts. Review the selected configuration and saved breakdowns in the browser.

## Continue in the browser

1. Sign in as `demo.payroll`, open November 2026 under monthly payroll, then
   review its prepared employee breakdowns and totals. Submit with a fictional
   reason and confirmation.
2. Sign in as `demo.approver` (separate browser profile or log out first), review,
   approve and separately finalize. The register, payslips, PDF and CSV then become
   available. The seeder never submits, approves or finalizes on your behalf.
3. To test correction versions, request correction of version 1 as Payroll Officer,
   reason `FICTIONAL: Ana's 1000.00 loan deduction was omitted`, authority
   `FICTIONAL-CORR-001`. Independently authorize as Approver. The new version is empty.
4. Prepare version 2 with all three employees again. Give Ana a fictional loan
   deduction of 1000.00, keep other original assumptions, recalculate/save reviews,
   submit and independently approve/finalize. Expected batch net is 75567.35 under
   the shipped reference profile; version 1 remains 76567.35.
5. Verify Viewer read-only access, preserved versions/documents and local integrity
   pages. Without a genuine blockchain adapter, expect Not anchored/Unavailable;
   the seeder makes no network call or fabricated transaction/verification.

## Reruns and existing data

Default `php artisan db:seed` remains empty; this fixture requires the explicit
class above. Reruns create no duplicate baseline data or reset existing passwords.
An existing owned November version-1 payroll is left entirely as you last saved
it, including coverage changes, inputs, submissions, finalization or corrections.
The seeder is not a reset/repair command and does not restore removed coverage.

Matching demo identifiers must still have the expected account roles/active
status, employee details, published salary sources and assignment ownership.
If these collide with unrelated or edited records, the seeder fails with a clear
conflict and rolls back all new fixture rows. It never overwrites those records,
truncates tables, removes history or changes statutory rules.

No demo data is automatically inserted simply by installing this code. Run the
explicit command to populate your local database. No production payroll, payment,
blockchain submission, approval or correction is seeded.
