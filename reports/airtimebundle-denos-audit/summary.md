# Airtimebundle Denomination Recovery Audit

Generated: 2026-09-05 16:09:55 UTC

## Summary

- Missing airtimebundle transactions: 0
- Missing timestamp batches: 0
- Safe single-row denos matches: 0
- Safe single-row drawer matches: 0
- Ambiguous shared batches: 0
- No-safe-evidence batches: 0

## Interpretation

- `SAFE_SINGLE_DENO_MATCH`: one missing transaction and one exact `denos` bucket match its totals.
- `SAFE_SINGLE_DRAWER_MATCH`: one missing transaction and one exact `drawer_denos` bucket match its totals.
- `AMBIGUOUS_SHARED_BATCH`: one historical bucket appears to cover multiple same-timestamp transactions, so auto-recovery would be guesswork.
- `NO_SAFE_EVIDENCE`: no unique exact history was found for a reliable backfill.

## Files

- `batches.csv`: one row per ambiguous timestamp batch.
- `transactions.csv`: one row per missing transaction for staff case-by-case review.

