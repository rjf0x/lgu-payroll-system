# Canonical payroll commitment v1

Phase 11 local-interface specification, 2026-10-27. This application-specific
format is **not RFC 8785**. A local digest does not establish blockchain anchoring
or integrity verification. No authorized test network is available.

The committed value is a JSON envelope containing exactly:

| Key | Value |
| --- | --- |
| `canonicalization` | String `payroll-commitment-json-v1` |
| `payroll_batch_id` | Integer database batch ID |
| `payroll_finalization_id` | Integer database finalization ID |
| `version` | Integer preserved finalization version |
| `snapshot` | Entire decoded `PayrollFinalization.snapshot`, including payroll, submission metadata and workflow evidence |

Serialize with these rules, recursively:

1. Retain JSON list order. Sort map keys using PHP `SORT_STRING` (binary string
   comparison); encode maps as objects even if their sorted keys are numeric.
2. Allow strings, integers, booleans, null and arrays only. Reject floating point,
   PHP objects/resources and invalid UTF-8. Monetary strings keep their exact
   two-decimal representation; do not convert them to JSON numbers.
3. Use PHP JSON escaping with `JSON_UNESCAPED_UNICODE`, `JSON_UNESCAPED_SLASHES`
   and `JSON_THROW_ON_ERROR`. Omit all whitespace outside string values. Do not
   normalize Unicode, trim strings, change line endings inside strings or reorder
   lists. The encoded document has no BOM or trailing newline.
4. Hash the resulting UTF-8 bytes using SHA-256. Store its 64 lowercase hex digits.

Input arrays follow Laravel's existing decoded snapshot convention: sequential
zero-based keys represent a list and all other arrays represent a map. The
existing associative JSON cast decodes both empty JSON objects and empty arrays
to `[]`; this format deliberately commits that decoded representation. It does
not commit the original database JSON text. Database JSON key order and formatting
therefore have no effect. Do not use this format for arbitrary external JSON.

The service first checks the supported finalized demonstration schema and amount
consistency using the existing frozen document projection. It checks batch,
version, period and submission scope, then serializes the **entire** saved
snapshot, including fields outside the document projection. It never reads live
employee, salary, input or rule values to construct the envelope. Snapshot floats
in any field fail closed. These structural checks do not authenticate a privileged
database rewrite.

Encoder test vector (without the application envelope): input keys `b: 2, a: 1`
produce exactly `{"a":1,"b":2}`. SHA-256:

```text
43258cff783fe7036d8a43033f830adfc60ec037382473548ac742b888292777
```

For an actual authorized fictional finalization, obtain the bytes in application
code with `PayrollAnchoringService::bytes($batch, $finalization)`. Independent
implementations must reproduce the envelope and rules above, rather than hash
SQL JSON text or a projected payslip. Keep those bytes confidential: they include
payroll information. Only the digest may cross the submission interface. The
random submission key is an off-chain retry identifier; it contains no payroll
identity or amounts and must not be added to the hash envelope.

One immutable `payroll_commitments` row is unique to each finalization, with a
frozen format, algorithm, digest and submission key. Existing Phase 9 currently
supports version 1 only; this phase adds no correction/reopen workflow. Any later
format must receive a new version identifier and keep the v1 implementation for
existing commitments. Never replace an earlier digest during retry.
