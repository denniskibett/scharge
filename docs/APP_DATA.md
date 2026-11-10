# App data that matters

*Added 2026-11-11.*

Four kinds of data from the Scharge app, chosen for their value: push notifications, payment tracking, on-site proof and crash reports. The app-side work (Flutter) is listed at the end.

## 1. Push notifications, before SMS

- **The phone's push address:** the app sends its Firebase token with `POST /api/v1/app/device` `{push_token, app_version, os_version, device_model}` on sign-in, and whenever Firebase rotates the token. A token is kept on one session only, so a second sign-in on the same phone moves it.
- **Sending:** `App\Services\App\PushService` (FCM HTTP v1, a service-account JWT exchanged at Google and cached for 50 minutes) sends to every phone of a person used in the last 30 days. Addresses Google reports as dead are removed.
- **Bills, reminders and bill links:** `BillNotifier` tries push first. If any phone gets it, the message is `channel = push` and **no SMS is sent**. Otherwise it falls back to SMS as before.
- **Receipts:** when a payment is recorded, the tenant gets a push saying "Payment received".
- **Setup:** Integrations › **scharge app** (platform): the Firebase service-account JSON. The same settings hold the **oldest app version allowed**; older apps get HTTP 426 `update_required` with the download link.

## 2. Payment attempts: why rent is not paid

`payment_attempts` stores one row per Pay press on a bill, from the website or the app. App pay links carry `&from=app`.
- **Statuses:** `started`, then `prompt_sent` (M-Pesa) or `redirected` (Sharet), then one of `paid`, `cancelled` (1032), `wrong_pin` (2001), `no_funds` (1), `timeout` (1037/1036), or `failed`.
- **How outcomes arrive:** STK outcomes come from `MpesaStk` updates (the callback); Sharet outcomes come from `SharetPaymentRequest` status changes.
- **Report:** Rent collection › **Payment attempts**, covering the last 30 days. It shows presses, paid and conversion, app against website, each outcome with KES, and the latest 40.

## 3. Proof of being on site

- **Meter readings:** `POST /api/v1/app/meters/{id}/readings` also takes `lat`, `lng`, `accuracy_m` and `captured_at` (stored on `meter_readings`), and returns them as `location`.
- **Repairs:** `POST /api/v1/app/repairs/{id}/visit` `{kind: checkin|checkout, lat, lng, accuracy_m, note}` writes to `maintenance_visits`.
  - A check-in starts an open job.
  - The response gives the minutes on site so far.
- **Gate:** guest-code use was already timed (`gate_passes.checked_in_at`).

Location is taken only at that moment, with the phone's permission, and never in the background.

## 4. Crash reports and versions

- **Crashes:** `POST /api/v1/app/errors` (signed in or not; up to 30 a minute) `{message, stack, screen, app_version, os_version, device_model}`. Errors with the same message and first stack line are grouped and counted.
- **Versions:** each call sends `X-App-Version`, `X-OS-Version` and `X-Device-Model`, stored on the phone's session.
- **Report:** Admin › **App health** (sysadmin) shows phones active in 30 days, how many can get push, app and Android versions, phone models, and errors (with stack and a "Mark fixed" button).

## App side (Flutter, `/Users/dennis/projects/scharge-app`)

1. Firebase Messaging: register `push_token` with `/device` on sign-in and on token refresh; show notifications; open the bill on tap (`data.invoice_id`).
2. Send `X-App-Version`, `X-OS-Version` and `X-Device-Model` on every call; on 426, show "Update the app" with `download_url`.
3. Pay: open `pay_url` as given (it already has `&from=app`).
4. Readings: send the phone's position and time with each reading; repairs get "I've arrived" and "Finished" buttons calling `/visit`. Ask for location only when needed, and say why on screen.
5. Report uncaught Flutter and Dart errors to `/errors`.

## Privacy

The data-processing agreement names the sub-processors, and should add Google Firebase (push) to that list. Ask for location and notification consent when the app first needs them. Readings' and visits' positions are kept with the record.
