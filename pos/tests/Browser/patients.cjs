const {
  chromium,
} = require("C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright");
const { randomUUID } = require("node:crypto");
const fs = require("node:fs");
const assert = require("node:assert/strict");
const base = "http://127.0.0.1:8147",
  directory = "temp/phase2884-patients-r3";
const ids = JSON.parse(fs.readFileSync(directory + "/identities.json", "utf8"));
const report = {
  scope:
    "Synthetic patients only; isolated SQLite; actual Chrome; no real health data/devices",
  cases: [],
  errors: [],
};
let currentPage;
async function login(browser, role, width = 1280) {
  const context = await browser.newContext({
    viewport: { width, height: 900 },
  });
  const page = await context.newPage();
  currentPage = page;
  page.on("pageerror", (error) => report.errors.push(error.message));
  await page.goto(base);
  const status = await page.evaluate(async (role) => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    return (
      await fetch("/login", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": token,
        },
        body: JSON.stringify({
          email: role + "@patient.fixture.test",
          password: "synthetic-patient-password",
        }),
      })
    ).status;
  }, role);
  assert.equal(status, 200);
  return { context, page };
}
async function request(page, path, body) {
  return page.evaluate(
    async ({ path, body }) => {
      const response = await fetch(path, {
        method: body === undefined ? "GET" : "POST",
        cache: "no-store",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          ...(body === undefined
            ? {}
            : {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                  'meta[name="csrf-token"]',
                ).content,
              }),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
      });
      return {
        status: response.status,
        data: await response.json(),
        cache: response.headers.get("Cache-Control"),
      };
    },
    { path, body },
  );
}
(async () => {
  const browser = await chromium.launch({
    executablePath:
      "C:/Program Files (x86)/Google/Chrome/Application/chrome.exe",
    headless: true,
  });
  try {
    const { context, page } = await login(browser, "pharmacist");
    await page.goto(base + "/patients");
    await page.locator("#load").click();
    await page.locator("#clinical").waitFor({ state: "visible" });
    await page.locator("#name").fill("SYNTHETIC-PATIENT-A-SECRET");
    await page.locator("#birth_date").fill("2000-01-02");
    await page.locator("#phone").fill("+66812345678");
    await page.locator("#allergies").fill("SYNTHETIC-ALLERGY-SECRET");
    await page.locator("#synthetic").check();
    let dropped = false;
    await page.route("**/patients", async (route) => {
      if (route.request().method() === "POST" && !dropped) {
        dropped = true;
        await route.fetch();
        await route.abort("failed");
      } else await route.continue();
    });
    await page.locator("#save").click();
    await page.waitForFunction(() => !document.querySelector("#save").disabled);
    await page.locator("#save").click();
    await page.waitForFunction(() =>
      document
        .querySelector("#message")
        .textContent.includes("บันทึก revision 1 แล้ว"),
    );
    const options = await request(page, "/patients/options");
    assert.match(options.cache, /no-store/);
    assert.equal(options.data.items.length, 1);
    const patientId = options.data.items[0].id;
    await page.locator("#search-term").fill("SYNTHETIC-PATIENT-A-SECRET");
    await page.locator("#search").click();
    await page.waitForFunction(() =>
      document
        .querySelector("#message")
        .textContent.includes("ค้นหาแบบ exact match แล้ว"),
    );
    assert.equal(await page.locator("#patients article").count(), 1);
    const original = await request(page, "/patients/" + patientId);
    assert.equal(original.data.profile.name, "SYNTHETIC-PATIENT-A-SECRET");
    const edited = {
      request_key: randomUUID(),
      expected_version: 1,
      synthetic_confirmed: true,
      profile: { ...original.data.profile, name: "SYNTHETIC-PATIENT-B-SECRET" },
    };
    delete edited.profile.synthetic;
    assert.equal(
      (await request(page, "/patients/" + patientId, edited)).status,
      200,
    );
    await page.locator("#phone").fill("+66899999999");
    await page.locator("#save").click();
    await page.waitForFunction(() =>
      document
        .querySelector("#message")
        .textContent.includes("Patient record changed"),
    );
    await page.locator("#load").click();
    await page.locator("#patients button").first().click();
    await page.waitForFunction(() =>
      document.querySelector("#code").textContent.includes("revision 2"),
    );
    assert.equal(await page.locator("#phone").inputValue(), "+66812345678");
    await page.locator("#synthetic").check();
    await page.locator("#occurred_on").fill("2026-01-01");
    await page.locator("#note").fill("SYNTHETIC-VISIT-ORIGINAL-SECRET");
    await page.locator("#visit").click();
    await page.waitForFunction(() =>
      document
        .querySelector("#message")
        .textContent.includes("เพิ่มบันทึกแล้ว"),
    );
    await page.locator("#visits").click();
    await page.waitForFunction(() =>
      document
        .querySelector("#timeline")
        .textContent.includes("SYNTHETIC-VISIT-ORIGINAL-SECRET"),
    );
    await page.locator("#timeline button").first().click();
    await page.locator("#note").fill("SYNTHETIC-VISIT-CORRECTION-SECRET");
    await page
      .locator("#correction_reason")
      .fill("Synthetic correction reason");
    await page.locator("#visit").click();
    await page.waitForFunction(() =>
      document
        .querySelector("#message")
        .textContent.includes("เพิ่มบันทึกแล้ว"),
    );
    await page.locator("#visits").click();
    await page.waitForFunction(
      () => document.querySelectorAll("#timeline article").length === 2,
    );
    const storage = await page.evaluate(async () => ({
      local: JSON.stringify(localStorage),
      session: JSON.stringify(sessionStorage),
      idb: await indexedDB.databases(),
      caches: await caches.keys(),
    }));
    assert.equal(storage.local.includes("SYNTHETIC-PATIENT"), false);
    assert.equal(storage.session.includes("SYNTHETIC-PATIENT"), false);
    assert.equal(storage.idb.length, 0);
    assert.equal(storage.caches.length, 0);
    await page.screenshot({
      path: directory + "/clinical-desktop.png",
      fullPage: true,
    });
    await context.setOffline(true);
    await page.waitForFunction(
      () => document.querySelector("#name").value === "",
    );
    assert.equal(await page.locator("#timeline").innerText(), "");
    await context.setOffline(false);
    await page.goto(base);
    await page.getByLabel("Terminal").selectOption(ids.pharmacist.terminal);
    await page.getByLabel("ผู้ป่วย", { exact: true }).waitFor();
    await page.getByLabel("ผู้ป่วย", { exact: true }).selectOption(patientId);
    await page
      .getByRole("button", { name: /Synthetic Patient Shop Item/ })
      .click();
    await page.getByLabel("รับเงินสด", { exact: true }).fill("20.00");
    await page
      .getByRole("button", { name: "ยืนยันรับเงินสด", exact: true })
      .click();
    await page
      .getByText("รวม 10.70 · รับ 20.00 · ทอน 9.30", { exact: true })
      .waitFor();
    await page.goto(base + "/patients");
    await page.locator("#load").click();
    await page.locator("#patients button").first().click();
    await page.waitForFunction(
      () =>
        document.querySelector("#name").value === "SYNTHETIC-PATIENT-B-SECRET",
    );
    await page.locator("#dispensing").click();
    await page.waitForFunction(() =>
      document
        .querySelector("#timeline")
        .textContent.includes("Synthetic Patient Shop Item"),
    );
    report.cases.push({
      name: "clinician create/lost-response replay/stale409/append correction/checkout/timeline/no offline storage",
      result: "PASS",
    });
    await context.close();
    for (const role of [
      "cashier",
      "manager",
      "qualified",
      "other",
      "retail",
      "restaurant",
    ]) {
      const { context, page } = await login(
        browser,
        role,
        role === "cashier" ? 375 : 1280,
      );
      if (["retail", "restaurant"].includes(role)) {
        const response = await page.goto(base + "/patients");
        assert.equal(response.status(), 404);
        assert.equal((await request(page, "/patients/options")).status, 404);
        await page.goto(base);
        await page
          .getByRole("button", { name: "ออกจากระบบ", exact: true })
          .waitFor();
        assert.equal(
          await page
            .getByRole("link", { name: "ประวัติผู้ป่วย", exact: true })
            .count(),
          0,
        );
      } else {
        await page.goto(base + "/patients");
        await page.locator("#load").click();
        await page.waitForFunction(
          () =>
            document
              .querySelector("#message")
              .textContent.includes("สิทธิ์อ่าน") ||
            document
              .querySelector("#message")
              .textContent.includes("เห็นเฉพาะ"),
        );
        const response = await request(page, "/patients/" + patientId);
        assert.equal(
          response.status,
          role === "qualified" ? 200 : role === "other" ? 404 : 403,
        );
        if (role === "cashier" || role === "manager") {
          assert.equal(await page.locator("#clinical").isVisible(), false);
          const text = await page.locator("#patients").innerText();
          assert.equal(text.includes("SYNTHETIC-PATIENT-B-SECRET"), false);
          assert.match(text, /S\*\*\*/);
        }
        if (role === "qualified") {
          await page.locator("#patients button").first().click();
          await page.waitForFunction(
            () =>
              document.querySelector("#name").value ===
              "SYNTHETIC-PATIENT-B-SECRET",
          );
        }
        if (role === "cashier") {
          await page.setViewportSize({ width: 375, height: 900 });
          assert.equal(
            await page.evaluate(
              () => document.documentElement.scrollWidth > innerWidth + 1,
            ),
            false,
          );
          await page.screenshot({
            path: directory + "/cashier-mobile.png",
            fullPage: true,
          });
        }
      }
      report.cases.push({ role, result: "PASS" });
      await context.close();
    }
    assert.deepEqual(report.errors, []);
    report.patient_id = patientId;
    report.result = "PASS";
  } catch (error) {
    report.result = "FAIL";
    report.error = error.stack;
    if (currentPage) {
      report.lastPage = await currentPage
        .locator("body")
        .innerText()
        .catch(() => null);
      await currentPage
        .screenshot({ path: directory + "/failure.png", fullPage: true })
        .catch(() => {});
    }
    console.error(error);
    process.exitCode = 1;
  } finally {
    await browser.close();
    fs.writeFileSync(
      directory + "/browser-report.json",
      JSON.stringify(report, null, 2),
    );
  }
})();
