# Rescuer account identifier corrections — 25 October 2026

Corrected 49 field responder codes in the operational database. The seeded `BDRRM-TEAM-###` values did not match the current validator and were blocking profile updates. Code allocation uses the existing team normalization and sequence generator, checks occupied codes and logins, and runs in one transaction with per-record audit entries.

| Canonical team | Corrected codes | Count |
| --- | --- | ---: |
| SRR | SRR-1000 through SRR-1005 | 6 |
| ECM | ECM-2000 through ECM-2006 | 7 |
| HFAPS | HFAPS-3000 through HFAPS-3005 | 6 |
| SST | SST-4000 through SST-4005 | 6 |
| FMT | FMT-5000 through FMT-5005 | 6 |
| RDT | RDT-6000 through RDT-6005 | 6 |
| RDANA | RDANA-9000 through RDANA-9005 | 6 |
| CWT | CWT-11000 through CWT-11005 | 6 |

These canonical prefixes follow the application's existing alias mappings: SAR→SRR, MED→HFAPS, EVC→ECM, SEC→SST, FIR→FMT, LOG→RDT, DANA→RDANA and COM→CWT. Team membership, team names and access roles were not changed by this correction.

Julius Ledesma: `BDRRM-DANA-006` → **`RDANA-9005`**.

The profile API now displays the corrected responder code as Account ID. Existing named login usernames and old BDRRM sign-in aliases remain valid. The new codes also resolve to the same linked user accounts. Internal responder IDs, user IDs, passwords, roles, team membership, assignments and administrator codes were preserved. Fingerprints of the relevant records matched before and after the operation.

Older open edit forms are handled: submitting the saved legacy alias is normalized to that record's corrected code, preventing the old form from restoring the invalid code. Existing headquarters identifiers are preserved when editing those records; the field-team format is not incorrectly applied to their saved identities. The numeric administrator-ID exception remains in place.

Verification confirmed zero remaining field code corrections, zero duplicate nonempty codes, and all 49 old/new sign-in alias pairs resolving to their original user IDs. No passwords were reset and no accounts were recreated. The normalization command is idempotent.

Commands for future audits:

```powershell
php artisan rescuers:normalize-codes
php artisan rescuers:normalize-codes --apply --output=path/to/corrected.csv
```

The roster seeder's misplaced helper method was repaired, and the temporary-account seeder now generates a canonical responder code. Neither seeder was run against operational accounts during this correction.

Evidence and the complete before/after mapping: `backend-laravel/docs/verification/responder-id-correction-2026-10-25/`. A copy of `corrected.csv` is available in Downloads as `RESQPERATION-Rescuer-ID-Corrections-2026-10-25.csv`.
