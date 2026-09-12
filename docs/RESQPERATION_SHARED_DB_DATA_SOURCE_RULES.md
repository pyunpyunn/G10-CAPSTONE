# RESQPERATION Shared DB Data Source Rules

This document is the rule for keeping the web system aligned with the currently connected database.

## Main Rule

The web application must display records from the database configured in:

```text
backend-laravel/.env
```

Every page should show records from the shared DB configured in `.env`.

Do not use local, old, prototype, temporary, or hardcoded records as visible fallback data for production pages.

## Allowed Fallbacks

These are allowed because they are not pretending to be real records:

- Empty states such as `No records found`.
- Error states such as `Data cannot be loaded right now`.
- Form placeholders such as `Search name, ID, team...`.
- Static UI labels, filters, button text, status colors, and validation messages.

## Not Allowed

These should not appear unless they are actually saved in the connected DB:

- Demo disaster events such as `DEV TEST`.
- Temporary household or rescuer accounts.
- Prototype names from old HTML files.
- Local-only generated households, rescuers, teams, or logs.
- Static rescue teams shown as if they are saved teams.
- Static map route start points shown as if they are real GPS records.

## Seeders

The default Laravel seeder is intentionally empty.

Do not run this on the shared DB expecting production data:

```bash
php artisan db:seed
```

Before running any one-time approved data script, confirm the active DB:

```bash
php artisan tinker --execute="echo DB::connection()->getDatabaseName();"
```

## Frontend Rule

The React web app should call Laravel API endpoints and render the response.

It should not:

- Import fake datasets.
- Keep page data in `localStorage`.
- Show static demo rows when API data is empty.
- Generate fake route points when the database has no GPS point.

The only expected browser storage is the auth token. After switching DBs, clear the token and log in again:

```js
localStorage.removeItem('resqperation_web_token')
```

## Backend Rule

Backend services should read from the connected database using Laravel query builder or models.

If a required table or column is missing, return a clear error or an empty state. Do not replace missing DB records with demo records.

For dropdowns and cards:

- Rescue teams come from `rescue_teams`.
- Rescuers come from `responders` and linked `users`.
- Households come from `households`, `household_members`, and `addresses`.
- Dispatch areas currently come from `addresses.purok_sitio` because the shared DB `puroks` table may be empty.
- Map markers come from rows with saved GPS coordinates.

## Purok / Area Note

Dispatch dropdowns should be based on actual shared DB household address data:

```text
addresses.purok_sitio
```

That means labels should be treated as dispatch areas, not guaranteed rows from the `puroks` table.

Use this SQL to inspect the true dispatch areas:

```sql
SELECT
    COALESCE(NULLIF(a.purok_sitio, ''), NULLIF(a.barangay_name, ''), 'Unassigned') AS dispatch_area,
    COUNT(*) AS household_count
FROM households h
LEFT JOIN addresses a ON a.address_id = h.address_id
WHERE h.deleted_at IS NULL
GROUP BY dispatch_area
ORDER BY dispatch_area;
```

## Development Rule

Code should stay beginner-readable:

- API controllers call service classes.
- Services explain where data comes from through method names and short comments.
- Frontend pages orchestrate API loading and pass response data to components.
- Components display the data they receive; they should not create hidden sample datasets.
