# HIL Mobile

Ionic React + Capacitor client for the Amref Training Database.

## Local Development

Install dependencies:

```bash
npm --prefix mobile install
```

Run the Ionic web dev server:

```bash
npm --prefix mobile run dev
```

The app defaults to `VITE_API_BASE_URL=http://localhost:8000`. You can also set the API URL on the login screen.

## Build

```bash
npm --prefix mobile run build
```

## Native Shells

After a successful build:

```bash
npm --prefix mobile run cap:add:android
npm --prefix mobile run cap:sync
```

For iOS, run `npm --prefix mobile run cap:add:ios` on macOS with Xcode installed.

## Backend Requirements

The Laravel app must expose:

- `GET /api/mobile/appearance`
- `POST /api/mobile/login`
- `GET /api/mobile/me`
- `POST /api/mobile/logout`
- `GET /api/mobile/participant-registration/options`
- `POST /api/mobile/participant-registration`
- `GET /api/mobile/training-event-join-request/options`
- `POST /api/mobile/training-event-join-request`
- `/api/v1` resource endpoints for authenticated dashboard, participants, and training events

Set `CORS_ALLOWED_ORIGINS` in the Laravel environment to include the Ionic dev origin, usually `http://localhost:8100`, and native WebView origins `capacitor://localhost` and `ionic://localhost`.

The mobile client fetches `/api/mobile/appearance` at startup and after API URL changes on the login screen. Logo URLs, favicon, colors, border radii, site name/tagline, and login copy are applied from Laravel Appearance settings.
