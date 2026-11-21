# Authentication & Authorization

**Stage:** 2 of the development plan
**Status:** Implemented for the mock-defense build
**Related:** `database-implementation.md`, `use-case-diagram.md`, `database-schema.md` §4–5

---

## 1. Authentication

Login is **username-based** against the documented `users` table.

| Concern | Implementation |
|---|---|
| Credentials | `username` + `password` (Laravel maps `password` to the `password_hash` column via `User::getAuthPassword()` and `getAuthPasswordName()`). |
| Account status | `account_status` must be `ACTIVE`; inactive accounts are blocked before credential verification. |
| Timing | `last_login_at` is recorded by the `Login` event listener. |
| Remember me | Supported (`remember_token`). |
| Error message | Deliberately generic ("These credentials do not match our records.") to avoid username enumeration. |
| Rate limiting | The `login` rate limiter allows **5 attempts per minute** per `username + IP`, applied with `throttle:login`. |

### Login flow

1. Validate `username` and `password`.
2. Look up the user by `username`.
3. If the account exists but is **not active**, record a `LOGIN_BLOCKED` audit entry and return the generic error.
4. `Auth::attempt()` verifies the password (and rehashes if required).
5. On success: regenerate the session, record `LOGIN` (and update `last_login_at`), and redirect to the intended URL (default `dashboard`).
6. On failure: the `Failed` event records `LOGIN_FAILED`.

### Existing-session checks and node status (F13)

Protected application routes, including `blockchain.status` at `/blockchain/status`, use authentication, `EnsureAccountIsActive` and `PreventSensitiveResponseCaching`. An account deactivated after login is logged out and its session invalidated before the status controller can contact the node. Browser requests redirect to sign-in; JSON requests return 403. Subsequent unauthenticated JSON requests return 401. Active accounts can read this limited connectivity indicator without a payroll-write or verification grant.

Successful and unavailable node responses carry `Cache-Control: no-store, private`, `Pragma: no-cache` and the existing no-sniff header. The response contains only `connected`, `status`, `block_number` and `scope: node_connectivity`; unavailable/malformed RPC evidence returns 503 without upstream details. The header polls without cache, labels the result **Node connected**, **Node unavailable** or **Node status unavailable**, and clears an earlier green result when a session check fails.

This indicator checks `eth_blockNumber` only. It does not check the registry bridge, signer, target identity, queue, contract readiness, transaction confirmation or payroll integrity. Use operational readiness checks and the payroll registration's current confirmation/integrity result for those decisions. A responding node can coexist with an unavailable bridge or an unconfirmed payroll.

### Audit events

All authentication activity is written to `audit_logs` (module `Authentication`) by
`App\Listeners\AuthenticationEventSubscriber`:

| Action | Trigger |
|---|---|
| `LOGIN` | Successful authentication (`Illuminate\Auth\Events\Login`). |
| `LOGIN_FAILED` | Failed credential check (`Illuminate\Auth\Events\Failed`). |
| `LOGIN_BLOCKED` | Inactive account rejected before verification. |
| `LOGOUT` | Session destroyed (`Illuminate\Auth\Events\Logout`). |

---

## 2. Authorization (RBAC)

Authorization uses the documented `roles`, `permissions`, `user_roles`, and
`role_permissions` tables.

- **Roles** are `App\Models\Role` constants: `Admin`, `Payroll Officer`, `Approver`, `Viewer`.
- **Permission names** are `App\Models\Permission` constants, listed by `Permission::names()`.
- Each permission name is registered as a **Gate ability** in `AppServiceProvider`, so any of
  these work:

```php
Gate::allows('payroll.approve');
$user->can(Permission::PAYROLL_APPROVE);
```

```blade
@can('payroll.approve') ... @endcan
```

```php
Route::post(...)->middleware('can:payroll.approve');
```

### Role middleware

`App\Http\Middleware\EnsureUserHasRole` is aliased as `role` and accepts one or more
role names (OR semantics):

```php
Route::middleware(['auth', 'role:Admin'])->group(function () { /* ... */ });
Route::middleware(['auth', 'role:Payroll Officer,Approver'])->group(function () { /* ... */ });
```

Unauthenticated requests receive `401`; authenticated users without a matching role
receive `403`.

### Helpers on `User`

| Method | Purpose |
|---|---|
| `hasRole(string $roleName): bool` | Whether the user holds a role. |
| `permissionNames(): Collection` | All permission names granted through roles (memoized per request). |
| `hasPermissionTo(string $permission): bool` | Whether a permission is granted. |
| `isActive(): bool` | Whether `account_status` is `ACTIVE`. |

### Separation of duties

No blanket administrator bypass is registered. The **Admin role deliberately does not hold**
`payroll.approve` or `payroll.reject`, so the "preparer/submitter cannot approve" rule
cannot be bypassed by administrative privilege. The cross-table enforcement
(approver ≠ `created_by`/`submitted_by`) is added in Stage 7.

---

## 3. Role → permission matrix

| Permission | Admin | Payroll Officer | Approver | Viewer |
|---|:--:|:--:|:--:|:--:|
| `users.manage` | ✅ | | | |
| `roles.manage` | ✅ | | | |
| `employees.view` | ✅ | ✅ | ✅ | ✅ |
| `employees.manage` | ✅ | ✅ | | |
| `attendance.manage` | ✅ | ✅ | | |
| `leave.manage` | ✅ | ✅ | | |
| `compensation.manage` | ✅ | ✅ | | |
| `payroll.prepare` | | ✅ | | |
| `payroll.submit` | | ✅ | | |
| `payroll.review` | | | ✅ | |
| `payroll.approve` | | | ✅ | |
| `payroll.reject` | | | ✅ | |
| `payroll.finalize` | | | ✅ | |
| `payroll.view` | ✅ | ✅ | ✅ | ✅ |
| `reports.generate` (payslip generation/publication) | ✅ | ✅ | ✅ | |
| `reports.export` (spreadsheet exports) | ✅ | ✅ | ✅ | ✅ |
| `audit.view` | ✅ | | | |
| `blockchain.verify` | ✅ | ✅ | ✅ | |

Defined in `database/seeders/RolePermissionSeeder.php`.

F08 separates spreadsheet exports from payslip writes. The Viewer can read existing
authorized outputs and export spreadsheets, but cannot generate or publish payslips.
The permission migration updates existing role grants without reseeding accounts;
queued payslip jobs recheck the requester's current write permission. Export history
and audit metadata are still recorded for authorized downloads.
The data migration deliberately retains the hardened grants on rollback and does
not automatically restore Viewer write access.

---

## 4. Key files

| File | Responsibility |
|---|---|
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Username login, status check, logout. |
| `app/Listeners/AuthenticationEventSubscriber.php` | Auth audit logging and `last_login_at`. |
| `app/Services/AuditLogger.php` | Writes structured `audit_logs` rows. |
| `app/Http/Middleware/EnsureUserHasRole.php` | `role` route middleware. |
| `app/Providers/AppServiceProvider.php` | Gate registration, auth event subscription, `login` rate limiter. |
| `app/Models/Permission.php` | Permission name constants + `names()`. |
| `app/Models/User.php` | Auth column mapping and permission helpers. |

---

## 5. Tests

- `tests/Feature/AuthenticationTest.php` — login screen uses username; active login + audit;
  wrong password + audit; inactive blocked; logout + audit; rate limiting.
- `tests/Feature/AuthorizationTest.php` — permission checks per role (including Admin's
  inability to approve), and `role` middleware allow/deny/redirect behavior.
