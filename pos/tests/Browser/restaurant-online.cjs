const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require(path.join(process.argv[2], "playwright"));
const base = "http://127.0.0.1:8128";
const phase = process.env.POS_BROWSER_PHASE_2885 === "1";
const output = path.resolve(
  __dirname,
  "../../../temp",
  phase ? "phase2885-restaurant-browser" : "phase28-pos-browser",
);
(async () => {
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const errors = [];
  let passivePolls = 0;
  try {
    async function surface(login) {
      const context = await browser.newContext({
        viewport: { width: login ? 1440 : 390, height: 1000 },
      });
      await context.route("**/*", (r) =>
        new URL(r.request().url()).origin === base ? r.continue() : r.abort(),
      );
      const page = await context.newPage();
      page.on("pageerror", (e) => errors.push(e.message));
      page.on("request", (r) => {
        if (r.headers()["x-pos-passive"] === "1") passivePolls++;
      });
      if (login) {
        await page.goto(base, { waitUntil: "networkidle" });
        await page
          .getByLabel("อีเมล", { exact: true })
          .fill("restaurant@pos.test");
        await page
          .getByLabel("รหัสผ่าน", { exact: true })
          .fill("local-fixture-password");
        await page
          .getByRole("button", { name: "เข้าสู่ระบบ", exact: true })
          .click();
        await page
          .getByRole("region", { name: "ร้านอาหาร", exact: true })
          .waitFor();
      }
      return page;
    }
    const staff = await surface(true);
    const kitchen = await surface(true);
    const panel = staff.getByRole("region", { name: "ร้านอาหาร", exact: true });
    await staff
      .getByRole("button", { name: "เปิดกะขาย", exact: true })
      .waitFor();
    await staff.getByLabel("เงินตั้งต้น").fill("0.00");
    await staff.getByRole("button", { name: "เปิดกะขาย", exact: true }).click();
    const table = `ONLINE-${Date.now()}`;
    await panel.getByLabel("ชื่อโต๊ะ", { exact: true }).fill(table);
    await panel.getByRole("button", { name: "เพิ่มโต๊ะ", exact: true }).click();
    if (phase) {
      const seating = panel.getByRole("region", {
        name: "คิวที่นั่งและจองโต๊ะ",
      });
      await seating.getByLabel("ประเภทคิว").selectOption("reservation");
      await seating
        .getByLabel("โต๊ะที่จอง")
        .selectOption({ label: `${table} (4 ที่)` });
      const localFuture = new Date(
        Date.now() + 60 * 60000 - new Date().getTimezoneOffset() * 60000,
      )
        .toISOString()
        .slice(0, 16);
      await seating.getByLabel("วันเวลานัดตามเวลาเครื่อง").fill(localFuture);
      await seating
        .getByLabel("ข้อมูลติดต่อสำหรับจอง")
        .fill("Synthetic booking");
      await seating.getByRole("button", { name: "บันทึกคิวที่นั่ง" }).click();
      const booked = seating
        .getByRole("listitem")
        .filter({ hasText: "Synthetic booking" });
      await booked.waitFor();
      await booked.getByRole("button", { name: "ยกเลิกคิว" }).click();
      await booked.waitFor({ state: "detached" });
      await seating.getByLabel("ประเภทคิว").selectOption("walk_in");
      await seating.getByRole("button", { name: "บันทึกคิวที่นั่ง" }).click();
      const waiting = seating
        .getByRole("listitem")
        .filter({ hasText: "Walk-in" });
      await waiting.getByRole("button", { name: "เรียกคิว" }).click();
      await waiting.getByLabel("โต๊ะสำหรับคิว").selectOption({ label: table });
      await waiting.getByRole("button", { name: "จัดที่นั่ง" }).click();
      await waiting.waitFor({ state: "detached" });
    }
    await panel
      .getByRole("button", { name: `เลือกโต๊ะ ${table}`, exact: true })
      .click();
    await panel
      .getByLabel("เมนูอาหาร", { exact: true })
      .selectOption({ label: "อาหารทดสอบ" });
    await panel
      .getByLabel("ขนาดและตัวเลือก", { exact: true })
      .selectOption("28000000-0000-4000-8000-000000000018");
    await staff
      .getByLabel("QR สำหรับโต๊ะ", { exact: true })
      .selectOption({ label: table });
    await staff
      .getByRole("button", { name: "สร้าง QR เมนูจำลอง", exact: true })
      .click();
    const link = staff.getByRole("link", {
      name: "เปิดเมนูจำลอง",
      exact: true,
    });
    await link.waitFor();
    const customer = await surface(false);
    const menuURL = new URL(await link.getAttribute("href"), base).href;
    await customer.goto(menuURL, { waitUntil: "networkidle" });
    await customer
      .getByLabel("จำนวน อาหารทดสอบ จานใหญ่ เพิ่มไข่", { exact: true })
      .fill("1");
    if (phase) {
      assert(
        await customer.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      );
      await customer.context().setOffline(true);
      await customer
        .getByText("ออฟไลน์ รายการยังรอส่งหรือยืนยันผลจาก POS Server")
        .waitFor();
      assert(
        await customer
          .getByRole("button", { name: "ส่งคำขอให้พนักงาน" })
          .isDisabled(),
      );
      await customer.context().setOffline(false);
      await customer.route(
        "**/menu/requests",
        async (route) => {
          const upstream = await route.fetch();
          assert.equal(upstream.status(), 200);
          await route.abort();
        },
        { times: 1 },
      );
      await customer.getByRole("button", { name: "ส่งคำขอให้พนักงาน" }).click();
      await customer
        .getByRole("button", { name: "ส่งคำขอเดิมอีกครั้ง" })
        .waitFor();
    }
    const requestResponse = customer.waitForResponse(
      (r) =>
        r.url().endsWith("/menu/requests") && r.request().method() === "POST",
    );
    await customer
      .getByRole("button", {
        name: phase ? "ส่งคำขอเดิมอีกครั้ง" : "ส่งคำขอให้พนักงาน",
        exact: true,
      })
      .click();
    const request = await (await requestResponse).json();
    assert.equal(request.status, "pending");
    const card = staff.locator("div.panel").filter({ hasText: request.id });
    await card
      .getByRole("button", { name: "รับออเดอร์เข้าครัว", exact: true })
      .waitFor({ timeout: 20000 });
    await card
      .getByRole("button", { name: "รับออเดอร์เข้าครัว", exact: true })
      .click();
    const board = kitchen.getByRole("region", {
      name: "ร้านอาหาร",
      exact: true,
    });
    await board
      .getByRole("button", { name: "ครัวรับออเดอร์", exact: true })
      .first()
      .waitFor({ timeout: 20000 });
    for (const name of [
      "ครัวรับออเดอร์",
      "เริ่มเตรียม",
      "อาหารพร้อม",
      "เสิร์ฟ / ส่งมอบ",
    ])
      await board.getByRole("button", { name, exact: true }).first().click();
    await panel
      .getByLabel("เลือกชำระ อาหารทดสอบ จานใหญ่ เพิ่มไข่", { exact: true })
      .waitFor({ timeout: 20000 });
    await panel
      .getByLabel("เลือกชำระ อาหารทดสอบ จานใหญ่ เพิ่มไข่", { exact: true })
      .check();
    await panel
      .getByRole("button", { name: "ตรวจยอดบิลอาหาร", exact: true })
      .click();
    await panel
      .getByRole("status")
      .filter({ hasText: "ยอดบิลอาหารรวม VAT 16.05" })
      .waitFor();
    if (phase) {
      await customer
        .getByRole("button", { name: "ตรวจสถานะ", exact: true })
        .click();
      await customer.getByText(/เสิร์ฟแล้ว 1/).waitFor();
      await customer.screenshot({ path: path.join(output, "qr-mobile.png") });
    }
    for (const width of [1440, 390]) {
      await staff.setViewportSize({ width, height: 1000 });
      assert(
        await staff.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
        `Restaurant online overflow ${width}`,
      );
      await staff.screenshot({
        path: path.join(output, `restaurant-online-${width}.png`),
        fullPage: true,
      });
    }
    await panel.getByLabel("รับเงินสดค่าอาหาร", { exact: true }).fill("20.00");
    const paid = staff.waitForResponse(
      (r) =>
        r.url().endsWith("/restaurant/checkout") &&
        r.request().method() === "POST",
    );
    await panel
      .getByRole("button", { name: "ยืนยันรับเงินค่าอาหาร", exact: true })
      .click();
    const response = await paid;
    assert.equal(response.status(), 200);
    const sale = await response.json();
    assert.equal(sale.total, "16.05");
    assert.equal(
      sale.lines[0].product_id,
      "28000000-0000-4000-8000-000000000018",
    );
    assert.deepEqual(sale.lines[0].allocations, []);
    await customer
      .getByRole("button", { name: "ตรวจสถานะ", exact: true })
      .click();
    await customer
      .getByRole("heading", { name: "ชำระเงินและรับสินค้าแล้ว", exact: true })
      .waitFor();
    await staff.getByRole("button", { name: "ปิด", exact: true }).click();
    await panel
      .getByRole("button", { name: `ปิดโต๊ะ ${table}`, exact: true })
      .click();
    await staff.getByLabel("ยอดนับจริง").fill("16.05");
    await staff
      .getByRole("button", { name: "ปิดกะและบันทึกผลต่าง", exact: true })
      .click();
    await staff.getByText("ผลต่างเงินสด: 0.00", { exact: true }).waitFor();
    assert(passivePolls > 0);
    assert.deepEqual(errors, []);
    const result = {
      qrTableScope: "pass",
      staffAcceptance: "pass",
      selectedOption: "large + egg",
      kitchenPolling: "pass",
      passivePolls,
      total: "16.05",
      customerCompleted: true,
      ...(phase
        ? {
            seating: "pass",
            offlineReconnectAndLostResponse: "pass",
            customerKitchenProgress: "pass",
          }
        : {}),
      responsive: [1440, 390],
      consoleErrors: errors,
    };
    fs.writeFileSync(
      path.join(output, "restaurant-online-result.json"),
      JSON.stringify(result, null, 2),
    );
    console.log(JSON.stringify(result));
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
