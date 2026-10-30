# Canonical payroll commitment v1

Phase 11 local-interface specification, 2026-10-27. This application-specific
format is **not RFC 8785**. A local digest does not establish blockchain anchoring
or integrity verification. BESU-01/02 subsequently established a local network
and registry; the Laravel transport remains unavailable.

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
frozen format, algorithm, digest and submission key. Phase 12-B subsequently added
authorized correction versions under separate finalized batches. Any later
format must receive a new version identifier and keep the v1 implementation for
existing commitments. Never replace an earlier digest during retry.

## BESU-03 - Exact byte retention

`payroll_snapshot_archives` now retains one immutable local archive per
finalization: `schema_version=finalized-payroll-v1`, canonicalization identifier,
`algorithm=sha256`, the exact `canonical_bytes` string and its lowercase hex
`sha256`. The canonical envelope already contains batch/finalization/version
references. Bytes use LONGTEXT with no JSON cast; they have no BOM or trailing
newline. Model update/delete guards, a unique finalization foreign key, restricted
parent deletion and a populated-table rollback guard retain the record.

New authorized finalizations create this archive in the same transaction as their
snapshot and workflow event. Archive failure rolls the entire finalization back.
This does not prepare a submission, queue a job or create a blockchain attempt.
Existing manual preparation copies the archived hash after checking the current
snapshot, archived bytes/hash and any prior commitment all agree.

`PayrollSnapshotService::currentBytes()` and the existing anchoring `bytes()`/
`digest()` methods deliberately recompute the current frozen JSON for comparison;
they do not substitute archived content and conceal a changed snapshot. Local
verification, document issuance and correction authorization check the archive
when present. An archive mismatch blocks documents/preparation/correction and
invalidates local verification, including before any manual commitment exists.
The anchoring page also suppresses actions for those mismatches. A privileged SQL
rewrite/deletion of all local evidence remains outside these application guards.

Legacy records retain their v1 hash behavior. From `application/`, use
`php artisan payroll:archive-snapshots` for a read-only validation, then
`php artisan payroll:archive-snapshots --apply` to capture matching bytes. Optional
`--finalization=ID` selects one record. Each record is locked/validated separately;
blocked records produce a nonzero command result and are never repaired. An apply
run may retain valid archives even if another record is blocked.

Existing commitments must match before capture; no historical hash, retry UUID,
JSON snapshot or version is rewritten. Without an earlier digest/archive, this
can only preserve the current frozen representation, not authenticate its history.
Legacy raw database JSON bytes were never retained and cannot be recovered by
claiming the normalized canonical archive was that original raw text.

Archive contents are confidential payroll data. Do not print/export them into
logs, Git or public chain metadata. Only digest bytes are appropriate for future
Solidity bytes32 encoding (`hex2bin` of 64 validated lowercase hex digits is 32
bytes). No EVM transaction is sent in BESU-03.
