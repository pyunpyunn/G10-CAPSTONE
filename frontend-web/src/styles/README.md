# Frontend style structure

- `foundation.css`: theme tokens and application-wide primitives.
- `typography.css`: compact spacing tokens and shared text emphasis.
- `responsive.css`: viewport gutters, safe bottom spacing, and mobile layout safeguards.
- `auth.css`: landing page and login styles.
- `shell.css`: application shell, shared UI, dashboard, and operations styles that span modules.
- `households.css`, `weather.css`, `broadcast.css`, `mapping.css`, and `dispatch.css`: page-module styles.

`App.css` is intentionally only the import manifest. Add new shared tokens to `foundation.css`; keep feature-specific selectors in the matching module stylesheet.
