const { remote } = require('webdriverio');
const fs = require('node:fs');
const path = require('node:path');

const appUrl = (process.env.APP_URL || 'https://serenesmile.vercel.app').replace(/\/$/, '');
const testUsername = process.env.TEST_ADMIN_USERNAME || '';
const testPassword = process.env.TEST_ADMIN_PASSWORD || '';
const artifactsDir = path.join(__dirname, 'artifacts');

function check(condition, message) {
  if (!condition) throw new Error(message);
}

async function textOf(driver, selector) {
  const element = await driver.$(selector);
  await element.waitForDisplayed({ timeout: 15000 });
  return element.getText();
}

async function submitLogin(driver, resultSelector) {
  await driver.$('button[type="submit"]').click();
  const submitted = await driver.waitUntil(async () => {
    return (await driver.$(resultSelector).isExisting()) || !(await driver.$('input[name="identity"]').isExisting());
  }, { timeout: 4000, interval: 250 }).then(() => true).catch(() => false);
  if (!submitted) await driver.execute(() => document.querySelector('form').requestSubmit());
}

async function run() {
  const driver = await remote({
    hostname: process.env.APPIUM_HOST || '127.0.0.1',
    port: Number(process.env.APPIUM_PORT || 4723),
    logLevel: process.env.WDIO_LOG_LEVEL || 'info',
    capabilities: {
      platformName: 'Android',
      browserName: 'Chrome',
      'wdio:enforceWebDriverClassic': true,
      'appium:automationName': 'UiAutomator2',
      'appium:deviceName': process.env.APPIUM_DEVICE_NAME || 'Android',
      'appium:noReset': true,
      'appium:newCommandTimeout': 120
    }
  });

  try {
    console.log('TEST 1: Login page loads in Android Chrome');
    await driver.url(`${appUrl}/login.php`);
    const heading = await textOf(driver, 'h2');
    check(heading === 'Welcome back', `Expected login heading, received: ${heading}`);
    check((await driver.getTitle()).includes('Serene Smile'), 'Page title does not identify Serene Smile');
    console.log('PASS: Login page loaded');

    console.log('TEST 2: Invalid credentials are rejected');
    await driver.$('input[name="identity"]').setValue('appium-invalid-user');
    await driver.$('input[name="password"]').setValue('invalid-password');
    await submitLogin(driver, '.alert.error');
    const error = await textOf(driver, '.alert.error');
    check(error.includes('incorrect'), `Expected invalid-login message, received: ${error}`);
    console.log('PASS: Invalid credentials were rejected');

    if (!testUsername || !testPassword) {
      console.log('SKIP: Valid login/navigation (set TEST_ADMIN_USERNAME and TEST_ADMIN_PASSWORD to enable)');
      return;
    }

    console.log('TEST 3: Administrator can sign in');
    await driver.$('input[name="identity"]').setValue(testUsername);
    await driver.$('input[name="password"]').setValue(testPassword);
    await submitLogin(driver, '.topbar h1');
    const dashboardHeading = await textOf(driver, '.topbar h1');
    check(dashboardHeading === 'Dashboard', `Expected Dashboard, received: ${dashboardHeading}`);
    console.log('PASS: Administrator signed in');

    console.log('TEST 4: Mobile navigation opens the Patients page');
    const menu = await driver.$('button.menu');
    if (await menu.isDisplayed()) await menu.click();
    await driver.$('a[href$="patients.php"]').click();
    const patientsHeading = await textOf(driver, '.topbar h1');
    check(patientsHeading === 'Patient Management', `Expected Patient Management, received: ${patientsHeading}`);
    console.log('PASS: Patients navigation works');
  } catch (error) {
    fs.mkdirSync(artifactsDir, { recursive: true });
    const screenshot = path.join(artifactsDir, `failure-${Date.now()}.png`);
    await driver.saveScreenshot(screenshot).catch(() => {});
    console.error(`Failure screenshot: ${screenshot}`);
    throw error;
  } finally {
    await driver.deleteSession();
  }
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
