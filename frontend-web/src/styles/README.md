# Frontend style structure

- `foundation.css`: theme tokens and application-wide primitives.
- `index.css`: global page/body/root resets, font defaults, and base typography behavior.
- `typography.css`: compact spacing tokens and shared text emphasis helpers.
- `layout.css`: header, sidebar, profile/dropdown, and notification shell styles.
- `main.css`: the main content area, page frame, refresh states, and general page containers.
- `dashboard.css`: dashboard overview cards, stat blocks, event banners, and operational panels.
- `notifications.css`: notification popovers and notifications page layouts.
- `profile.css`: profile page and account detail styles.
- `auth.css`: landing page and login styles.
- `responsive.css`: viewport gutters, safe bottom spacing, and mobile layout safeguards.
- `households.css`, `weather.css`, `broadcast.css`, `mapping.css`, and `dispatch.css`: feature/module styles.

`App.css` is intentionally only the import manifest. Add new shared tokens to `foundation.css`; keep feature-specific selectors in the matching module stylesheet.
