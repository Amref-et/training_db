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

The app defaults to `VITE_API_BASE_URL=https://et-dhis.amref.org/hil2`. You can also set the Laravel URL on the login screen.

For an installed Android APK, do not use `localhost` unless Laravel is running on the device itself. Use the Laravel server address reachable from the phone, for example `https://et-dhis.amref.org/hil2`. The app accepts either the Laravel app root or the same URL ending in `/api`.

## Build

```bash
npm --prefix mobile run build
```

## Offline Behavior

The mobile app caches successful GET responses in Capacitor Preferences and reuses them when the API is unreachable. This covers appearance, logged-in user details, dashboard, participants, events, registration options, join-request options, and training workflow views after they have been loaded once.

The app queues JSON write requests while offline and retries them when the device comes back online. Queued actions include participant registration, join requests, enrolment, workflow approvals/rejections, workshop count changes, workshop score entry, and closeout status/removal updates.

Closeout report and picture uploads require an active connection because file `FormData` is not persisted in the offline queue.

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

Set `CORS_ALLOWED_ORIGINS` in the Laravel environment to include the Ionic dev origin, usually `http://localhost:8100`, and native WebView origins `https://localhost`, `capacitor://localhost`, and `ionic://localhost`. The installed Android APK uses Capacitor native HTTP for API calls, which avoids WebView CORS failures, but the origins are still useful for browser and WebView compatibility.

The mobile client fetches `/api/mobile/appearance` at startup and after API URL changes on the login screen. Logo URLs, favicon, colors, border radii, site name/tagline, and login copy are applied from Laravel Appearance settings.
