# Serene Smile Appium Testing Guide

This project uses Appium, UiAutomator2, WebdriverIO, and Chrome on an Android emulator to test the deployed Serene Smile website.

## What is one-time setup?

You only need to do these once on a computer:

1. Install Android Studio.
2. Install an Android SDK, Platform Tools, Emulator, and a Google APIs/Play Store system image.
3. Create an Android Virtual Device (AVD).
4. Configure `ANDROID_HOME`, `ANDROID_SDK_ROOT`, and `JAVA_HOME`.
5. Install Appium:

   ```powershell
   npm install --global appium
   ```

6. Install and validate UiAutomator2:

   ```powershell
   appium driver install uiautomator2
   appium driver doctor uiautomator2
   ```

7. Install the project dependencies from the project folder:

   ```powershell
   cd C:\xampp\htdocs\Serene
   npm install
   ```

The doctor should report `0 required fixes needed`. Optional warnings about FFmpeg, GStreamer, or bundletool do not block these browser tests.

## Normal workflow for every testing session

### 1. Start the Android emulator

Open Android Studio, then open **Device Manager** and start `Medium_Phone_API_37.0`.

On this computer, the emulator is more reliable with software graphics. If starting it from Device Manager crashes or leaves the device offline, run this in PowerShell instead:

```powershell
emulator -avd Medium_Phone_API_37.0 -no-snapshot-load -gpu swiftshader_indirect -cores 4 -memory 2048
```

Wait until the Android home screen appears. If Android asks **Allow USB debugging?**, select **Always allow from this computer** and click **Allow**.

Verify the device in PowerShell:

```powershell
adb devices
```

Expected result:

```text
emulator-5554    device
```

Do not continue if the result is `unauthorized` or `offline`.

### 2. Start the Appium server

Open PowerShell terminal 1 and run:

```powershell
appium server --allow-insecure="uiautomator2:chromedriver_autodownload"
```

Keep this terminal open. The server should listen at:

```text
http://127.0.0.1:4723
```

The `chromedriver_autodownload` option lets Appium download a ChromeDriver matching the Chrome version installed in the emulator.

The project test configuration forces classic WebDriver because UiAutomator2 does not implement every WebDriver BiDi browser command.

### 3. Configure the test session

Open PowerShell terminal 2:

```powershell
cd C:\xampp\htdocs\Serene
$env:APP_URL="https://serenesmile.vercel.app"
```

Use the public production URL. A protected Vercel preview URL redirects Android Chrome to **Log in to Vercel** and cannot test the application without a protection-bypass token.

To include valid-login and authenticated navigation tests, add the administrator credentials:

```powershell
$env:TEST_ADMIN_USERNAME="your-admin-username"
$env:TEST_ADMIN_PASSWORD="your-admin-password"
```

To allow the full suite to create and update test records, set this explicit safety switch:

```powershell
$env:RUN_CRUD_TESTS="true"
```

These variables exist only in the current terminal. Never save the real password in Git or in the test source code.

### 4. Run the tests

In terminal 2:

```powershell
npm run test:appium
```

The test automates Chrome in the emulator and checks:

1. The login page loads.
2. Invalid credentials are rejected.
3. A configured administrator can log in.
4. The dashboard loads.
5. Mobile navigation opens Patient Management.
6. Patient create, search/read, and update.
7. Dentist create, read, update, and deactivate (soft delete).
8. Dental Service create, read, update, and delete.
9. Appointment create, approve, and complete workflow.

Expected result:

```text
PASS: Login page loaded
PASS: Invalid credentials were rejected
PASS: Administrator signed in
PASS: Patients navigation works
```

If the admin variables are omitted, authenticated tests are skipped. If `RUN_CRUD_TESTS=true` is omitted, database-changing tests are skipped. Patients have no hard-delete feature, dentists use deactivation, and appointments preserve status history, so the suite tests the lifecycle the application actually supports.

### 5. End the session

After the test completes:

1. Press `Ctrl+C` in terminal 1 to stop Appium.
2. Close the emulator or stop it from Android Studio Device Manager.
3. Close terminal 2 to clear its temporary credentials.

## Test evidence

Terminal `PASS` output can be captured for the test report. When a test assertion fails, the script saves a screenshot under:

```text
appium\artifacts\
```

The artifacts directory is ignored by Git.

## Common errors

### Device is unauthorized

```text
emulator-5554    unauthorized
```

Accept the USB debugging prompt inside the emulator, then run `adb devices` again.

### Appium connection refused

```text
ECONNREFUSED 127.0.0.1:4723
```

Start the Appium server in terminal 1 and leave it running.

### No ChromeDriver can automate Chrome

```text
No Chromedriver found that can automate Chrome
```

Restart Appium using:

```powershell
appium server --allow-insecure="uiautomator2:chromedriver_autodownload"
```

Verify that the server startup output lists:

```text
uiautomator2:chromedriver_autodownload
```

Then rerun `npm run test:appium`. The first matching-driver download can take several minutes.

### Test uses the wrong website

Set the correct deployed URL before running:

```powershell
$env:APP_URL="https://your-current-domain.vercel.app"
```

If Android Chrome displays **Log in to Vercel**, the selected deployment has Vercel Deployment Protection enabled. Use `https://serenesmile.vercel.app` for this project.

### Emulator closes, becomes offline, or shows a graphics error

Start the AVD with the software graphics workaround:

```powershell
emulator -avd Medium_Phone_API_37.0 -no-snapshot-load -gpu swiftshader_indirect -cores 4 -memory 2048
```

### Valid-login test is skipped

Set both `TEST_ADMIN_USERNAME` and `TEST_ADMIN_PASSWORD` in the same terminal used to run the test.

## Files involved

- Test script: `appium/serene-smile.test.js`
- npm commands and dependency: `package.json`
- Failure screenshots: `appium/artifacts/`
