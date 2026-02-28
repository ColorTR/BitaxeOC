# RUN 04 - WS-02 CSV Upload + Parser Fuzzing

Date (UTC): 2026-02-24
Workstream: WS-02 (P0)
Scope: `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/app/Analyzer.php`

## Test Design

Used reflection invocation of `Analyzer::parseCsv(string)` with adversarial corpus:

- baseline valid CSV
- BOM + locale comma/semicolon styles
- huge hash field (`250k` chars)
- invalid UTF-8 in cell
- null byte suffix
- broken quote row
- delimiter chaos
- locale alias mismatch
- dense 30k-row input for truncation/time-budget behavior

Script:
- `/tmp/ws02_fuzz_v2.php`

## Result Matrix (selected)

- `basic_ok`: rows=2, parsedRows=2, skippedRows=0
- `bom_locale`: rows=2, parsedRows=2
- `huge_field_hash`: rows=0, skippedRows=1 (graceful drop)
- `invalid_utf8_cell`: no crash, rows=0
- `null_byte_suffix`: rows=1, parsedRows=1
- `broken_quotes`: rows=1, skippedRows=1
- `delimiter_chaos`: rows=1, skippedRows=1
- `locale_mix_weird`: rows=0, missingRequiredColumns includes `missing_frequency`
- `timeout_dense` (30k rows): parsedRows=10000, truncatedRows=20000, peak delta ~4MB

## Findings

- No fatal crash/panic path found under tested malformed corpus.
- Parser degrades gracefully by skipping malformed rows.
- Row cap (`csv_max_data_rows`) truncation behaves deterministically.
- Reflection helper in PHP 8.5 emits deprecation if `setAccessible()` is called; test harness adjusted accordingly.

## WS-02 Exit Criteria

- deterministic valid output: PASS
- graceful invalid reject: PASS
- limit enforcement without runaway memory: PASS (within tested corpus)
