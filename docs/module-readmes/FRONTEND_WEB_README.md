# RESQPERATION Web Frontend

This folder contains the React + Vite HQ/Admin web dashboard.

## Current Scope

- Login screen connected to Laravel API auth.
- Protected HQ/Admin app shell.
- Prototype-matched sidebar and topbar.
- Beginner shared UI components in `src/components/ui`.
- Live dashboard page connected to `/api/v1/dashboard`.

## Dashboard Rule

The dashboard must not show hardcoded disaster data. If there is no active disaster event, it should show standby and empty states.

Current dashboard data comes from the shared MySQL database through Laravel:

```text
GET /api/v1/dashboard
```

## Useful Commands

```bash
npm run lint
npm run build
npm run dev
```
