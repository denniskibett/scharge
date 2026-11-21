# scharge app

One Android app for every scharge role: tenants, owners and landlord staff, meter readers, maintenance,
security, BnB hosts and scharge staff. Each person signs in once with their phone number and an SMS code, creates a
PIN, then opens the app with their fingerprint, face or phone lock. The server decides which screens each person gets.

- Server API and the screens-per-role list: `docs/MOBILE_APP.md` in the scharge repo.
- Designs: the "7 · Mobile app" page of the scharge brand canvas (scharge red, Unbounded headings, DM Sans text).

## Why Flutter

- Builds a native Android APK with no web view. The project is Android only (no `ios/` folder, by choice);
  `flutter create --platforms ios .` adds iOS back later from the same code.
- Fingerprint / face / phone lock (`local_auth`), the phone's keystore (`flutter_secure_storage`) and the camera
  (`image_picker`) are first-class packages.
- It was already installed on the build Mac with the Android SDK, so `flutter build apk` works with no extra setup.
  (Capacitor would reuse web code, but scharge has no mobile web front end to reuse, and native unlock and camera
  work is simpler in Flutter.)

## Layout

```
lib/main.dart                 root: phone number -> create PIN -> unlock -> tabs from the server
lib/core/api.dart             HTTP client (API_BASE), errors
lib/core/session.dart         token in the keystore, profile, screens, brand, PIN and phone-lock unlock
lib/core/theme.dart           brand colours and type; a white label swaps the red
lib/auth/auth_screens.dart    phone, SMS code, pick account, email sign-in, create PIN, unlock
lib/tenant/tenant_screens.dart home and bills, pay, receipts, repairs
lib/staff/staff_screens.dart  rent, arrears, payments, tenants, units, companies, readings + photo, work orders, bookings
lib/shared/gate_screen.dart   gate passes for guards, tenants and hosts
```

## Build

Needs Flutter 3.47+ and the Android SDK (`flutter doctor`).

```bash
flutter pub get
flutter build apk --debug                        # build/app/outputs/flutter-apk/app-debug.apk
```

Point it at another server (staging, or your Mac from the Android emulator):

```bash
flutter build apk --debug --dart-define=API_BASE=https://staging.example.test/api/v1/app
flutter run --dart-define=API_BASE=http://10.0.2.2:8000/api/v1/app
```

The default is `https://scharge.sharet.africa/api/v1/app`, which works only once the server side is deployed.

## Versions for current and older phones

Android 7 (2016) and newer. Release builds come in three files:

```bash
flutter build apk --release --split-per-abi --target-platform android-arm,android-arm64 --obfuscate --split-debug-info=build/symbols
flutter build apk --release --target-platform android-arm,android-arm64 --obfuscate --split-debug-info=build/symbols
```

| File | For | Size |
|---|---|---|
| `app-arm64-v8a-release.apk` | current phones (64-bit, most phones since about 2017) | about 19 MB |
| `app-armeabi-v7a-release.apk` | older and budget phones (32-bit) | about 16 MB |
| `app-release.apk` | any phone: both of the above in one file | about 34 MB |

Keep `build/symbols` with each release: it turns crash reports from the obfuscated build back into readable ones.
The debug APK (`flutter build apk --debug`) is much bigger (about 160 MB) and only for testing.

## Sign a release APK

1. Make a key once and keep it safe (losing it means users must uninstall to update):
   ```bash
   keytool -genkey -v -keystore ~/keys/scharge-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias scharge
   ```
2. Create `android/key.properties` (never commit it):
   ```
   storePassword=...
   keyPassword=...
   keyAlias=scharge
   storeFile=/Users/you/keys/scharge-upload.jks
   ```
3. Build:
   ```bash
   flutter build apk --release --split-per-abi     # smaller APKs per phone type
   flutter build appbundle --release               # for the Play Store
   ```
   Without `key.properties` the release build is signed with the debug key: fine for testing, not for the Play Store.

## Put the APKs on the website

```bash
cp build/app/outputs/flutter-apk/app-release.apk ../scharge/public/downloads/scharge-app.apk
cp build/app/outputs/flutter-apk/app-arm64-v8a-release.apk ../scharge/public/downloads/scharge-app-arm64.apk
cp build/app/outputs/flutter-apk/app-armeabi-v7a-release.apk ../scharge/public/downloads/scharge-app-arm32.apk
```

Then deploy scharge as usual (`scripts/push.sh`). The API & white label and Developers pages show the download
links for whichever of those files exist. Moving from a debug-key build to a properly signed one needs testers to uninstall first.

## Push notifications (Firebase)

Bills, reminders, bill links and receipts arrive as push notifications; the server falls back to SMS for phones
without push. To switch push on:

1. In the scharge Firebase project (console.firebase.google.com) add an Android app with package name
   `africa.sharet.scharge_app`.
2. Download its `google-services.json` and put it at `android/app/google-services.json` (do not commit it).
3. Rebuild. The Gradle plugin is applied only when that file exists, so builds without it still work and push stays off.
4. On the server: Integrations › scharge app, paste the Firebase service-account JSON (scharge docs/APP_DATA.md).

The app asks for notification permission the first time someone signs in, then sends its push address with
`POST /api/v1/app/device`, and again whenever Firebase changes it. Tapping a bill notification opens that bill.

## Data the app sends

- Every call: `X-App-Version`, `X-OS-Version`, `X-Device-Model`. An HTTP 426 answer shows "Update the app" with the
  server's download link.
- Uncaught errors: `POST /api/v1/app/errors` (message, stack, screen, versions), signed in or not. Not in debug builds.
- Location: only at the moment a meter reading is saved or a repair visit starts or ends ("I've arrived",
  "Finished"), after a prompt that says why. Never in the background. Saying no still saves the reading or visit.

## App icon

`android/app/src/main/res/mipmap-*`: the scharge "s" on scharge red, from the brand logo (canvas "scharge brand").
Android 8+ uses the adaptive icon (`mipmap-anydpi-v26/ic_launcher.xml`). The launch screen is red with the same mark.
A white-label company's own launcher icon needs its own build (a different app id and icon); in-app colours and
logo already follow the company.

## Where the code lives and versions

This app is the `mobile` branch of github.com/denniskibett/scharge: its own history, never merged into the website
branches. `pubspec.yaml` holds the one version (`X.Y.Z+N`, `+N` up by one for every build that leaves the Mac); every
release has a `## X.Y.Z+N (date)` section in `CHANGELOG.md` and a tag `mobile-vX.Y.Z+N`. `scripts/release.sh` checks
that, builds the APKs and tags. Published APKs: `/home/scharge.sharet.africa/public_html/downloads/` (arm64, arm32 and
universal).
