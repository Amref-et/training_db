# Legacy HIL Import Preparation

This project includes a prep command for legacy High Impact Leadership data that arrives as a MySQL dump instead of the normalized CSVs used by the current app.

## Command

```powershell
php artisan hil:prepare-legacy-import "D:\HIL\hil.sql"
```

Optional output directory:

```powershell
php artisan hil:prepare-legacy-import "D:\HIL\hil.sql" --output="D:\HIL\prepared-upload"
```

## What It Produces

The command writes a preparation package under `storage/app/import-prep/...` unless `--output` is provided.

Generated files:

- `organizations_ready.csv`
  Import this first with `php artisan organizations:import-hierarchy`.
- `organizations_review.csv`
  Geography rows that should not be imported without manual cleanup.
- `participants_ready.csv`
  Rows already shaped for the current participant CSV import.
- `participants_review.csv`
  Rows blocked by invalid email, missing geography, unresolved profession, or incomplete name split.
- `participants_staging.csv`
  Full transformed participant extract with issue annotations.
- `training_events_staging.csv`
  Event-level grouping for later training-event import or scripted migration.
- `capstone_projects_staging.csv`
  Legacy capstone project notes and participant linkage for later migration.
- `missing_professions.csv`
  Profession values from the legacy dump that do not currently exist in the app.
- `README.md`
  Summary counts and recommended upload order.
- `manifest.json`
  Machine-readable summary of the generated package.

## Current Mapping Rules

- Legacy `training_participants` is transformed into normalized participant CSV rows.
- `participant_code` is left blank so the current app auto-generates it.
- `date_of_birth` and `age` are left blank unless the legacy source contains a safe value.
- Emails are lightly normalized by trimming spaces and fixing commas in the domain portion.
- Phone numbers are stripped to digits and `+`.
- `National` rows with placeholder zone and woreda values are normalized to `National / National / National` so they can be staged consistently.
- Participant names are split into `first_name`, `father_name`, and `grandfather_name` when possible. Rows that do not split cleanly are routed to review.

## Recommended Workflow

1. Run the prep command.
2. Import `organizations_ready.csv`.
3. Fix `participants_review.csv` and any missing professions.
4. Import `participants_ready.csv`.
5. Use `training_events_staging.csv` to create or script the training events.
6. Migrate `capstone_projects_staging.csv` after participant reconciliation.
