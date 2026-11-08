const { remote } = require('webdriverio');
const fs = require('node:fs');
const path = require('node:path');

const baseUrl = (process.env.APP_URL || 'https://serenesmile.vercel.app').replace(/\/$/, '');
const username = process.env.TEST_ADMIN_USERNAME || '';
const password = process.env.TEST_ADMIN_PASSWORD || '';
const allowChanges = /^(1|true|yes)$/i.test(process.env.RUN_CRUD_TESTS || '');
const runId = Date.now().toString().slice(-10);
const artifacts = path.join(__dirname, 'artifacts');
const testData = {
  patient: { first: 'Appium', last: `Patient-${runId}`, email: `patient.${runId}@example.com`, phone: `09${runId.slice(-9)}` },
  dentist: { first: 'Appium', last: `Dentist-${runId}`, email: `dentist.${runId}@example.com`, license: `APP-${runId}` },
  service: { name: `Appium Cleaning ${runId}`, updated: `Appium Cleaning Updated ${runId}` }
};

function assert(condition, message) { if (!condition) throw new Error(message); }
async function shown(driver, selector) {
  const element = await driver.$(selector);
  await element.waitForDisplayed({ timeout: 15000 });
  return element;
}
async function text(driver, selector) { return (await shown(driver, selector)).getText(); }
async function open(driver, file, heading) {
  await driver.url(`${baseUrl}/${file}`);
  assert((await text(driver, '.topbar h1')) === heading, `${heading} did not load`);
}
async function row(driver, ...values) {
  const conditions = values.map((value) => `contains(normalize-space(.), "${value}")`).join(' and ');
  return shown(driver, `//table//tr[${conditions}]`);
}
async function submit(driver, form) {
  await form.$('button[type="submit"], button:not([type])').click();
  const submitted = await driver.waitUntil(async () => {
    return (await driver.$('.alert.success').isExisting()) || (await driver.$('.alert.error').isExisting());
  }, { timeout: 4000, interval: 250 }).then(() => true).catch(() => false);
  if (!submitted) {
    await driver.execute((targetForm) => targetForm.requestSubmit(), form);
  }
}
async function pass(id, message) { console.log(`PASS ${id}: ${message}`); }
async function submitLogin(driver, resultSelector) {
  await driver.$('button[type="submit"]').click();
  const submitted = await driver.waitUntil(async () => {
    return (await driver.$(resultSelector).isExisting()) || !(await driver.$('input[name="identity"]').isExisting());
  }, { timeout: 4000, interval: 250 }).then(() => true).catch(() => false);
  if (!submitted) {
    await driver.execute(() => document.querySelector('form').requestSubmit());
  }
}

async function authenticate(driver) {
  console.log('TC-01 Login page validation');
  await driver.url(`${baseUrl}/login.php`);
  assert((await text(driver, 'h2')) === 'Welcome back', 'Login heading missing');
  assert((await driver.getTitle()).includes('Serene Smile'), 'Incorrect page title');
  await pass('TC-01', 'Login page loaded');

  console.log('TC-02 Invalid login');
  await driver.$('input[name="identity"]').setValue('appium-invalid-user');
  await driver.$('input[name="password"]').setValue('invalid-password');
  await submitLogin(driver, '.alert.error');
  assert((await text(driver, '.alert.error')).toLowerCase().includes('incorrect'), 'Invalid login was not rejected');
  await pass('TC-02', 'Invalid credentials rejected');

  if (!username || !password) {
    console.log('SKIP TC-03 onward: Set TEST_ADMIN_USERNAME and TEST_ADMIN_PASSWORD');
    return false;
  }
  console.log('TC-03 Valid administrator login');
  await driver.$('input[name="identity"]').setValue(username);
  await driver.$('input[name="password"]').setValue(password);
  await submitLogin(driver, '.topbar h1');
  assert((await text(driver, '.topbar h1')) === 'Dashboard', 'Dashboard did not load');
  await pass('TC-03', 'Administrator signed in');

  console.log('TC-04 Mobile navigation');
  const menu = await driver.$('button.menu');
  if (await menu.isDisplayed()) await menu.click();
  await driver.$('a[href$="patients.php"]').click();
  assert((await text(driver, '.topbar h1')) === 'Patient Management', 'Patients page did not load');
  await pass('TC-04', 'Patient navigation works');
  return true;
}

async function patients(driver) {
  console.log('TC-05 Create patient');
  await open(driver, 'patients.php', 'Patient Management');
  let form = await shown(driver, 'section.card form.form-grid');
  await form.$('input[name="first_name"]').setValue(testData.patient.first);
  await form.$('input[name="last_name"]').setValue(testData.patient.last);
  await form.$('input[name="email"]').setValue(testData.patient.email);
  await form.$('input[name="phone"]').setValue(testData.patient.phone);
  await form.$('input[name="address"]').setValue('Appium automated test record');
  await submit(driver, form);
  assert((await text(driver, '.alert.success')).includes('saved'), 'Patient save failed');
  await pass('TC-05', 'Patient created');

  console.log('TC-06 Search/read patient');
  await driver.$('form.filters input[name="q"]').setValue(testData.patient.last);
  await driver.$('form.filters button').click();
  await row(driver, testData.patient.last, testData.patient.email);
  await pass('TC-06', 'Patient found');

  console.log('TC-07 Update patient');
  let record = await row(driver, testData.patient.last);
  await record.$('a=Edit').click();
  form = await shown(driver, 'section.card form.form-grid');
  const updatedPhone = `08${runId.slice(-9)}`;
  await form.$('input[name="phone"]').setValue(updatedPhone);
  await form.$('input[name="address"]').setValue('Updated by Appium');
  await submit(driver, form);
  await row(driver, testData.patient.last, updatedPhone);
  await pass('TC-07', 'Patient updated');
  console.log('INFO TC-08: Patient hard-delete is not a feature; test record is retained for history');
}

async function dentists(driver) {
  console.log('TC-09 Create dentist');
  await open(driver, 'dentists.php', 'Dentist Management');
  let form = await shown(driver, 'section.card form.form-grid');
  await form.$('input[name="first_name"]').setValue(testData.dentist.first);
  await form.$('input[name="last_name"]').setValue(testData.dentist.last);
  await form.$('input[name="email"]').setValue(testData.dentist.email);
  await form.$('input[name="specialization"]').setValue('Automation Dentistry');
  await form.$('input[name="license_number"]').setValue(testData.dentist.license);
  await submit(driver, form);
  assert((await text(driver, '.alert.success')).includes('saved'), 'Dentist save failed');
  await pass('TC-09', 'Dentist created');

  console.log('TC-10 Read/update dentist');
  let record = await row(driver, testData.dentist.last, testData.dentist.license);
  await record.$('a=Edit').click();
  form = await shown(driver, 'section.card form.form-grid');
  await form.$('input[name="specialization"]').setValue('Updated Automation Dentistry');
  await submit(driver, form);
  record = await row(driver, testData.dentist.last, 'Updated Automation Dentistry');
  await pass('TC-10', 'Dentist read and updated');

  console.log('TC-11 Deactivate dentist (soft delete)');
  await record.$('button=Deactivate').click();
  await row(driver, testData.dentist.last, 'Inactive');
  await pass('TC-11', 'Dentist deactivated');
}

async function services(driver) {
  console.log('TC-12 Create service');
  await open(driver, 'services.php', 'Dental Services');
  let form = await shown(driver, 'section.card form.form-grid');
  await form.$('input[name="name"]').setValue(testData.service.name);
  await form.$('input[name="price"]').setValue('750');
  await form.$('input[name="duration_minutes"]').setValue('45');
  await form.$('textarea[name="description"]').setValue('Temporary Appium CRUD test service');
  await submit(driver, form);
  assert((await text(driver, '.alert.success')).includes('saved'), 'Service save failed');
  await pass('TC-12', 'Service created');

  console.log('TC-13 Read/update service');
  let record = await row(driver, testData.service.name, '750.00', '45 min');
  await record.$('a=Edit').click();
  form = await shown(driver, 'section.card form.form-grid');
  await form.$('input[name="name"]').setValue(testData.service.updated);
  await form.$('input[name="price"]').setValue('850');
  await submit(driver, form);
  record = await row(driver, testData.service.updated, '850.00');
  await pass('TC-13', 'Service read and updated');

  console.log('TC-14 Delete unused service');
  await record.$('button=Remove').click();
  await driver.acceptAlert().catch(() => {});
  await driver.pause(1000);
  assert(!(await driver.$(`//table//tr[contains(normalize-space(.), "${testData.service.updated}")]`).isExisting()), 'Service still exists');
  await pass('TC-14', 'Service deleted');
}

async function appointments(driver) {
  console.log('TC-15 Create appointment');
  await open(driver, 'appointments.php', 'Appointment Management');
  const form = await shown(driver, 'section.card form.form-grid');
  const patient = await form.$('select[name="patient_id"]');
  const dentist = await form.$('select[name="dentist_id"]');
  const service = await form.$('select[name="service_id"]');
  const ready = (await patient.$$('option')).length > 1 && (await dentist.$$('option')).length > 1 && (await service.$$('option')).length > 1;
  if (!ready) {
    console.log('SKIP TC-15 to TC-17: Existing patient, active dentist, and active service are required');
    return;
  }
  await patient.selectByIndex(1);
  await dentist.selectByIndex(1);
  await service.selectByIndex(1);
  const patientName = (await patient.$('option:checked').getText()).trim();
  const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  await form.$('input[name="appointment_date"]').setValue(tomorrow);
  await form.$('input[name="start_time"]').setValue('15:00');
  await form.$('textarea[name="notes"]').setValue(`Appium workflow ${runId}`);
  await submit(driver, form);
  assert((await text(driver, '.alert.success')).includes('created'), 'Appointment create failed');
  await pass('TC-15', 'Pending appointment created');

  console.log('TC-16 Approve appointment');
  let record = await row(driver, patientName, 'Pending', '3:00 PM');
  await record.$('button=Approve').click();
  record = await row(driver, patientName, 'Approved', '3:00 PM');
  await pass('TC-16', 'Appointment approved');

  console.log('TC-17 Complete appointment');
  await record.$('button=Complete').click();
  await row(driver, patientName, 'Completed', '3:00 PM');
  await pass('TC-17', 'Appointment completed');
}

async function run() {
  const driver = await remote({
    hostname: process.env.APPIUM_HOST || '127.0.0.1', port: Number(process.env.APPIUM_PORT || 4723),
    logLevel: process.env.WDIO_LOG_LEVEL || 'info',
    capabilities: { platformName: 'Android', browserName: 'Chrome', 'wdio:enforceWebDriverClassic': true,
      'appium:automationName': 'UiAutomator2', 'appium:deviceName': process.env.APPIUM_DEVICE_NAME || 'Android',
      'appium:noReset': true, 'appium:newCommandTimeout': 180 }
  });
  try {
    const loggedIn = await authenticate(driver);
    if (!loggedIn) return;
    if (!allowChanges) {
      console.log('SKIP TC-05 to TC-17: Set RUN_CRUD_TESTS=true to allow database-changing tests');
      return;
    }
    await patients(driver);
    await dentists(driver);
    await services(driver);
    await appointments(driver);
    console.log('ALL ENABLED APPIUM TESTS PASSED');
  } catch (error) {
    fs.mkdirSync(artifacts, { recursive: true });
    const screenshot = path.join(artifacts, `failure-${Date.now()}.png`);
    await driver.saveScreenshot(screenshot).catch(() => {});
    console.error(`Failure screenshot: ${screenshot}`);
    throw error;
  } finally { await driver.deleteSession(); }
}

run().catch((error) => { console.error(error); process.exitCode = 1; });
