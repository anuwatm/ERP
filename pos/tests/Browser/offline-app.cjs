const {
  chromium,
} = require("C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright");
const fs = require("node:fs");
const assert = require("node:assert/strict");
const run = process.env.POS_OFFLINE_BROWSER_RUN || "r5";
assert.match(run, /^[a-z0-9-]+$/);
const directory = "temp/phase2883-offline-" + run;
const ids = JSON.parse(fs.readFileSync(directory + "/identities.json", "utf8"));
const report = {
  scope:
    "Synthetic SQLite, actual Chrome PWA and encrypted queue; no real equipment",
  cases: [],
  errors: [],
};
let currentPage;
(async () => {
  const browser = await chromium.launch({
    executablePath:
      "C:/Program Files (x86)/Google/Chrome/Application/chrome.exe",
    headless: true,
  });
  try {
    for (const [type, id] of Object.entries(ids)) {
      const context = await browser.newContext({
        viewport:
          type === "pharmacy"
            ? { width: 375, height: 900 }
            : { width: 1280, height: 900 },
      });
      const page = await context.newPage();
      currentPage = page;
      page.on("pageerror", (error) =>
        report.errors.push(type + ": " + error.message),
      );
      await page.goto("http://127.0.0.1:8146/");
      const login = await page.evaluate(async (type) => {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        return (
          await fetch("/login", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": csrf,
              Accept: "application/json",
            },
            body: JSON.stringify({
              email: type + "@offline.fixture.test",
              password: "synthetic-offline-password",
            }),
          })
        ).status;
      }, type);
      assert.equal(login, 200);
      await page.goto("http://127.0.0.1:8146/offline/app");
      await page.locator("#connect").click();
      await page.waitForFunction(() =>
        document
          .querySelector("#message")
          .textContent.includes("ยืนยัน session แล้ว"),
      );
      await page.locator("#passphrase").fill("synthetic device unlock");
      await page.locator("#initialize").click();
      await page.waitForFunction(() =>
        document
          .querySelector("#message")
          .textContent.includes("ปลดล็อกคิวแล้ว"),
      );
      await page.evaluate(() =>
        Promise.race([
          navigator.serviceWorker.ready,
          new Promise((_, reject) =>
            setTimeout(
              () => reject(new Error("Service worker installation timed out")),
              15000,
            ),
          ),
        ]),
      );
      if (type !== "restaurant") {
        await page
          .locator("#quota")
          .fill(
            JSON.stringify([{ product_id: id.product, quantity: "2.0000" }]),
          );
        await page.locator("#permit").click();
        await page.waitForFunction(() =>
          document
            .querySelector("#message")
            .textContent.includes("รับ permit และเวลาใหม่แล้ว"),
        );
      }
      await context.setOffline(true);
      if (type === "restaurant") {
        await page
          .locator("#draft-kind")
          .selectOption("restaurant.order.draft");
        await page
          .locator("#draft-note")
          .fill("Synthetic kitchen request only");
        await page.locator("#draft").click();
        await page.waitForFunction(() =>
          document
            .querySelector("#queue")
            .textContent.includes("restaurant.order.draft"),
        );
      } else {
        await page.locator("#cash").click();
        await page.waitForFunction(() =>
          document
            .querySelector("#message")
            .textContent.includes("บันทึกหลักฐานสำเร็จ"),
        );
        assert.match(await page.locator("#queue").innerText(), /1070|10\.70/);
      }
      for (const theme of ["light", "dark"]) {
        await page.evaluate(
          (value) => localStorage.setItem("pos-theme", value),
          theme,
        );
        await page.reload();
        await page.waitForSelector("#unlock");
        assert.equal(
          await page
            .locator("html")
            .evaluate((root) => getComputedStyle(root).colorScheme),
          theme,
        );
        assert.equal(
          await page.evaluate(
            () => document.documentElement.scrollWidth > innerWidth + 1,
          ),
          false,
        );
        assert.deepEqual(
          await page.evaluate(async () => {
            const sample = "ทดสอบ POS";
            const normal = await document.fonts.load(
              '400 16px "Sarabun"',
              sample,
            );
            const bold = await document.fonts.load(
              '700 16px "Sarabun"',
              sample,
            );
            return [normal.length > 0, bold.length > 0];
          }),
          [true, true],
        );
        await page.screenshot({
          path: directory + "/" + type + "-offline-" + theme + ".png",
        });
      }
      await page.locator("#passphrase").fill("synthetic device unlock");
      await page.locator("#unlock").click();
      await page.waitForFunction(() =>
        document.querySelector("#queue article"),
      );
      if (type !== "restaurant") {
        await page.locator("#cash").click();
        await page.waitForFunction(() =>
          document
            .querySelector("#message")
            .textContent.includes("ต้องรับรองเวลาออนไลน์ใหม่"),
        );
      }
      assert.equal(await page.locator("#queue article").count(), 1);
      await context.setOffline(false);
      let dropped = false;
      await page.route("**/offline/inbox", async (route) => {
        if (!dropped) {
          dropped = true;
          await route.fetch();
          await route.abort("failed");
        } else await route.continue();
      });
      await page.locator("#sync").click();
      await page.waitForFunction(
        () => !document.querySelector("#sync").disabled,
      );
      await page.locator("#refresh").click();
      await page.waitForFunction(() =>
        document.querySelector("#queue").textContent.includes("failed"),
      );
      await page.locator("#sync").click();
      await page.waitForFunction(() =>
        document.querySelector("#queue").textContent.includes("review"),
      );
      if (type !== "restaurant") {
        await page.locator("#review").click();
        await page.waitForSelector("#reviews article");
        await page
          .locator("#manager-password")
          .fill("synthetic-offline-password");
        await page
          .locator("#reviews input")
          .fill("Synthetic cash inspected and received into current shift");
        await page.locator("#reviews button").click();
        await page.waitForFunction(() =>
          document
            .querySelector("#message")
            .textContent.includes("ตรวจรับแล้ว"),
        );
        await page.locator("#sync").click();
        await page.waitForFunction(() =>
          document.querySelector("#queue").textContent.includes("synced"),
        );
        await page.locator("#leases").click();
        await page.waitForSelector("#leases-list article");
        await page
          .locator(
            '#leases-list input[type="text"], #leases-list input:not([type])',
          )
          .fill("All encrypted device records checked; remaining quota unused");
        const expiry = await page.locator("#permit-info").innerText();
        const until = expiry.split("\n")[1].replace("หมดอายุ ", "");
        await page.waitForTimeout(
          Math.max(0, Date.parse(until) - Date.now() + 1500),
        );
        await page.locator('#leases-list input[type="checkbox"]').check();
        await page
          .locator("#manager-password")
          .fill("synthetic-offline-password");
        await page.locator("#leases-list button").click();
        await page.waitForFunction(() =>
          document
            .querySelector("#message")
            .textContent.includes("ปิด permit แล้ว"),
        );
      }
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > innerWidth + 1,
      );
      assert.equal(overflow, false);
      const cacheUrls = await page.evaluate(async () => {
        const urls = [];
        for (const key of await caches.keys()) {
          for (const r of await (await caches.open(key)).keys())
            urls.push(new URL(r.url).pathname);
        }
        return urls;
      });
      const fonts = [
        "/fonts/sarabun-regular-v1.ttf",
        "/fonts/sarabun-bold-v1.ttf",
      ];
      assert(fonts.every((path) => cacheUrls.includes(path)));
      assert.equal(
        cacheUrls.every(
          (p) =>
            p === "/offline/app" ||
            p.startsWith("/build/assets/") ||
            fonts.includes(p),
        ),
        true,
      );
      assert.equal(await page.locator('meta[name="csrf-token"]').count(), 0);
      report.cases.push({
        type,
        offlineReload: true,
        offlineThemes: ["light", "dark"],
        lostResponseReplayed: true,
        financial:
          type === "restaurant" ? "request only" : "manager accepted once",
        cacheUrls,
        overflow,
      });
      await page.screenshot({
        path: directory + "/" + type + ".png",
        fullPage: true,
      });
      await context.close();
    }
    assert.deepEqual(report.errors, []);
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
    fs.writeFileSync(
      directory + "/browser-report.json",
      JSON.stringify(report, null, 2),
    );
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
