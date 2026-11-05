const {
  chromium,
} = require("C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright");
const ts = require("../../../backend/node_modules/typescript");
const http = require("node:http");
const fs = require("node:fs");
const assert = require("node:assert/strict");
const directory = "temp/phase2883-storage";
fs.mkdirSync(directory, { recursive: true });
const runDirectory = directory + "/" + Date.now();
fs.mkdirSync(runDirectory);
const compiled = ["EncryptedQueue", "TrustedClock", "QueueSync"]
  .map(
    (name) =>
      ts.transpileModule(
        fs.readFileSync("pos/resources/offline/" + name + ".ts", "utf8"),
        {
          compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.CommonJS,
          },
        },
      ).outputText,
  )
  .join("\n");
fs.writeFileSync(
  directory + "/vault.js",
  "var OfflineQueue = (() => {const exports = {};" +
    compiled +
    ";return exports;})();",
);
const server = http.createServer((req, res) => {
  res.setHeader("Cache-Control", "no-store");
  if (req.url === "/vault.js") {
    res.setHeader("Content-Type", "text/javascript");
    res.end(fs.readFileSync(directory + "/vault.js"));
  } else if (req.url === "/") {
    res.setHeader("Content-Type", "text/html");
    res.end(
      '<!doctype html><meta charset="utf-8"><title>Synthetic encrypted queue fixture</title><script src="/vault.js"></script>',
    );
  } else {
    res.writeHead(404);
    res.end();
  }
});
const scope = {
  instanceId: "33333333-3333-4333-8333-333333333333",
  branchId: "44444444-4444-4444-8444-444444444444",
  staffId: "55555555-5555-4555-8555-555555555555",
  terminalId: "66666666-6666-4666-8666-666666666666",
  deviceId: "77777777-7777-4777-8777-777777777777",
};
(async () => {
  await new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(8145, "127.0.0.1", resolve);
  });
  let context;
  const report = {
    scope:
      "Actual Chrome IndexedDB/WebCrypto, permit slot/quota and ACK primitives; server financial/PWA acceptance recorded separately",
    cases: [],
  };
  const launch = async () =>
    chromium.launchPersistentContext(runDirectory + "/chrome-profile", {
      executablePath:
        "C:/Program Files (x86)/Google/Chrome/Application/chrome.exe",
      headless: true,
    });
  try {
    context = await launch();
    let page = await context.newPage();
    await page.goto("http://127.0.0.1:8145/");
    const first = await page.evaluate(async (scope) => {
      const vault = await OfflineQueue.EncryptedQueue.open(scope);
      await vault.initialize("synthetic unlock phrase", {
        maxTransactions: 2,
        maxAmountCents: "10000",
      });
      const storage = await OfflineQueue.EncryptedQueue.storage();
      const entry = await vault.enqueue({
        kind: "sale.draft",
        amountCents: "5000",
        data: { note: "synthetic confidential evidence" },
      });
      vault.lock();
      let locked = false;
      try {
        await vault.entries();
      } catch {
        locked = true;
      }
      await vault.unlock("synthetic unlock phrase");
      const recovered = await vault.entries();
      const opening = indexedDB.open("company-pos-offline-v1");
      const db = await new Promise((resolve) => {
        opening.onsuccess = () => resolve(opening.result);
      });
      const dump = await Promise.all(
        ["vaults", "records"].map(
          (name) =>
            new Promise((resolve) => {
              const r = db.transaction(name).objectStore(name).getAll();
              r.onsuccess = () => resolve(r.result);
            }),
        ),
      );
      db.close();
      vault.close();
      return {
        id: entry.id,
        locked,
        recovered: recovered.length,
        persistent: storage.persistent,
        headroomKnown: storage.freeBytes > 0,
        dump: JSON.stringify(dump),
      };
    }, scope);
    assert.equal(first.locked, true);
    assert.equal(first.recovered, 1);
    assert.equal(first.headroomKnown, true);
    assert.equal(first.dump.includes("synthetic confidential evidence"), false);
    assert.equal(first.dump.includes("synthetic unlock phrase"), false);
    report.cases.push({
      name: "durable encrypted write and lock",
      result: "PASS",
      persistent: first.persistent,
    });
    await context.close();
    context = await launch();
    page = await context.newPage();
    await page.goto("http://127.0.0.1:8145/");
    const restarted = await page.evaluate(
      async ({ scope, id }) => {
        const vault = await OfflineQueue.EncryptedQueue.open(scope);
        let wrong = false;
        try {
          await vault.unlock("incorrect unlock phrase");
        } catch {
          wrong = true;
        }
        await vault.unlock("synthetic unlock phrase");
        const recovered = await vault.entries();
        const second = await OfflineQueue.EncryptedQueue.open(scope);
        await second.unlock("synthetic unlock phrase");
        const results = await Promise.allSettled([
          vault.enqueue({ kind: "sale.draft", amountCents: "4000", data: {} }),
          second.enqueue({ kind: "sale.draft", amountCents: "4000", data: {} }),
        ]);
        const rows = await vault.entries();
        const other = await OfflineQueue.EncryptedQueue.open({
          ...scope,
          branchId: "88888888-8888-4888-8888-888888888888",
        });
        let isolated = false;
        try {
          await other.unlock("synthetic unlock phrase");
        } catch {
          isolated = true;
        }
        const result = {
          wrong,
          isolated,
          idPreserved: recovered[0].id === id,
          count: rows.length,
          fulfilled: results.filter((r) => r.status === "fulfilled").length,
        };
        const opening = indexedDB.open("company-pos-offline-v1");
        const db = await new Promise((resolve) => {
          opening.onsuccess = () => resolve(opening.result);
        });
        await new Promise((resolve, reject) => {
          const tx = db.transaction("records", "readwrite");
          tx.objectStore("records").delete(rows[0].id);
          tx.oncomplete = resolve;
          tx.onerror = () => reject(tx.error);
        });
        let missingDetected = false;
        try {
          await vault.entries();
        } catch {
          missingDetected = true;
        }
        let blockedAfterLoss = false;
        try {
          await vault.enqueue({
            kind: "sale.draft",
            amountCents: "1",
            data: {},
          });
        } catch {
          blockedAfterLoss = true;
        }
        result.missingDetected = missingDetected;
        result.blockedAfterLoss = blockedAfterLoss;
        db.close();
        vault.close();
        second.close();
        other.close();
        return result;
      },
      { scope, id: first.id },
    );
    assert.equal(restarted.wrong, true);
    assert.equal(restarted.isolated, true);
    assert.equal(restarted.idPreserved, true);
    assert.equal(restarted.count, 2);
    assert.equal(restarted.fulfilled, 1);
    assert.equal(restarted.missingDetected, true);
    assert.equal(restarted.blockedAfterLoss, true);
    report.cases.push({
      name: "browser restart, incorrect passphrase, scope isolation and atomic cross-tab cap",
      result: "PASS",
    });
    const sync = await page.evaluate(async (scope) => {
      const queue = await OfflineQueue.EncryptedQueue.open({
        ...scope,
        deviceId: crypto.randomUUID(),
      });
      await queue.initialize("synthetic sync unlock", {
        maxTransactions: 5,
        maxAmountCents: "10000",
      });
      const entry = await queue.enqueue({
        kind: "sale.draft",
        amountCents: "500",
        data: { note: "immutable evidence" },
      });
      const inbox = new Map();
      let calls = 0,
        auth = 0;
      const runner = new OfflineQueue.QueueSync(
        queue,
        scope.deviceId,
        async () => {
          auth++;
        },
        async (payload) => {
          calls++;
          const serialized = JSON.stringify(payload);
          const old = inbox.get(payload.client_transaction_id);
          if (old && old.serialized !== serialized)
            throw new Error("Identity changed");
          const ack = old?.ack || {
            inbox_id: crypto.randomUUID(),
            client_transaction_id: payload.client_transaction_id,
            status: "review",
            sale_id: null,
            financial_posted: false,
          };
          inbox.set(payload.client_transaction_id, { serialized, ack });
          if (calls === 1)
            throw new Error("Simulated lost response after durable intake");
          return ack;
        },
      );
      try {
        await runner.run();
      } catch {}
      const failed = (await queue.entries())[0].status;
      queue.lock();
      await queue.unlock("synthetic sync unlock");
      await runner.run();
      const reviewed = (await queue.entries())[0];
      let invalidAck = false;
      try {
        await queue.transition(entry.id, "synced", {
          inbox_id: crypto.randomUUID(),
          client_transaction_id: crypto.randomUUID(),
          status: "synced",
          sale_id: crypto.randomUUID(),
          financial_posted: true,
        });
      } catch {
        invalidAck = true;
      }
      const ack = {
        ...reviewed.acknowledgement,
        status: "synced",
        sale_id: crypto.randomUUID(),
        financial_posted: true,
      };
      await queue.transition(entry.id, "synced", ack);
      await queue.transition(entry.id, "pending");
      const final = (await queue.entries())[0];
      queue.close();
      return {
        failed,
        reviewed: reviewed.status,
        final: final.status,
        auth,
        calls,
        inbox: inbox.size,
        id: final.id === entry.id,
        invalidAck,
      };
    }, scope);
    assert.equal(sync.failed, "failed");
    assert.equal(sync.reviewed, "review");
    assert.equal(sync.final, "synced");
    assert.equal(sync.inbox, 1);
    assert.equal(sync.calls, 2);
    assert.equal(sync.auth, 2);
    assert.equal(sync.id, true);
    assert.equal(sync.invalidAck, true);
    report.cases.push({
      name: "encrypted ACK state machine, lost response, reauthentication, immutable identity and final ACK replay",
      result: "PASS",
    });
    const cash = await page.evaluate(async (scope) => {
      const cashScope = { ...scope, deviceId: crypto.randomUUID() };
      const a = await OfflineQueue.EncryptedQueue.open(cashScope);
      await a.initialize("synthetic cash unlock", {
        maxTransactions: 2,
        maxAmountCents: "3000",
      });
      const product = crypto.randomUUID();
      const permit = {
        id: crypto.randomUUID(),
        instance_id: scope.instanceId,
        branch_id: scope.branchId,
        staff_id: scope.staffId,
        terminal_id: scope.terminalId,
        device_id: cashScope.deviceId,
        expires_at: new Date(Date.now() + 900000).toISOString(),
        policy: {
          version: 1,
          max_offline_transactions: 2,
          max_offline_amount: "30.00",
        },
        items: [
          {
            product_id: product,
            name: "Synthetic",
            unit_price: "10.00",
            tax_rate: "7.00",
            quantity: "2.0000",
          },
        ],
        slots: [
          { id: crypto.randomUUID(), ordinal: 1 },
          { id: crypto.randomUUID(), ordinal: 2 },
        ],
      };
      await a.installPermit(permit);
      await a.installPermit(
        Object.fromEntries(Object.entries(permit).reverse()),
      );
      let immutable = false;
      try {
        await a.installPermit({
          ...permit,
          items: [{ ...permit.items[0], quantity: "100.0000" }],
        });
      } catch {
        immutable = true;
      }
      const b = await OfflineQueue.EncryptedQueue.open(cashScope);
      await b.unlock("synthetic cash unlock");
      const input = {
        kind: "sale.draft",
        amountCents: "0",
        data: {
          offline_cash: true,
          clock: { eligible: true, permitId: permit.id },
          items: [{ product_id: product, quantity: "1.5000" }],
        },
      };
      const writes = await Promise.allSettled([
        a.enqueue(input, () => true),
        b.enqueue(input, () => true),
      ]);
      const entries = await a.entries();
      let blocked = false;
      try {
        await a.enqueue(input, () => false);
      } catch {
        blocked = true;
      }
      a.close();
      b.close();
      return {
        accepted: writes.filter((x) => x.status === "fulfilled").length,
        count: entries.length,
        amount: entries[0].amountCents,
        slot: entries[0].data.slot_id === permit.slots[0].id,
        blocked,
        immutable,
      };
    }, scope);
    assert.equal(cash.accepted, 1);
    assert.equal(cash.count, 1);
    assert.equal(cash.amount, "1605");
    assert.equal(cash.slot, true);
    assert.equal(cash.blocked, true);
    assert.equal(cash.immutable, true);
    report.cases.push({
      name: "atomic permit slot, exact decimal VAT, per-SKU quota and cross-tab cash cap",
      result: "PASS",
    });
    const failures = await page.evaluate(async (scope) => {
      const q = await OfflineQueue.EncryptedQueue.open({
        ...scope,
        deviceId: crypto.randomUUID(),
      });
      await q.initialize("synthetic failure unlock", {
        maxTransactions: 3,
        maxAmountCents: "3000",
      });
      const input = {
        kind: "sale.draft",
        amountCents: "500",
        data: { note: "retain original" },
      };
      await q.enqueue(input);
      const original = navigator.storage.estimate;
      navigator.storage.estimate = async () => ({ quota: 1, usage: 1 });
      let full = false;
      try {
        await q.enqueue(input);
      } catch {
        full = true;
      } finally {
        navigator.storage.estimate = original;
      }
      const transaction = IDBDatabase.prototype.transaction;
      IDBDatabase.prototype.transaction = function (...args) {
        const tx = transaction.apply(this, args);
        if (
          args[1] === "readwrite" &&
          Array.isArray(args[0]) &&
          args[0].includes("records")
        )
          queueMicrotask(() => tx.abort());
        return tx;
      };
      let aborted = false;
      try {
        await q.enqueue(input);
      } catch {
        aborted = true;
      } finally {
        IDBDatabase.prototype.transaction = transaction;
      }
      const retained = (await q.entries()).length;
      const opening = indexedDB.open("company-pos-offline-v1");
      const db = await new Promise((resolve) => {
        opening.onsuccess = () => resolve(opening.result);
      });
      const id = (await q.entries())[0].id;
      const row = await new Promise((resolve) => {
        const request = db
          .transaction("records")
          .objectStore("records")
          .get(id);
        request.onsuccess = () => resolve(request.result);
      });
      await new Promise((resolve, reject) => {
        const tx = db.transaction("vaults", "readwrite");
        tx.objectStore("vaults").delete(row.scope);
        tx.oncomplete = resolve;
        tx.onabort = () => reject(tx.error);
      });
      let metadataLoss = false;
      try {
        await q.initialize("different synthetic unlock", {
          maxTransactions: 3,
          maxAmountCents: "3000",
        });
      } catch {
        metadataLoss = true;
      }
      const remaining = await new Promise((resolve) => {
        const request = db
          .transaction("records")
          .objectStore("records")
          .index("scope")
          .count(row.scope);
        request.onsuccess = () => resolve(request.result);
      });
      db.close();
      q.close();
      return { full, aborted, retained, metadataLoss, remaining };
    }, scope);
    assert.equal(failures.full, true);
    assert.equal(failures.aborted, true);
    assert.equal(failures.retained, 1);
    assert.equal(failures.metadataLoss, true);
    assert.equal(failures.remaining, 1);
    report.cases.push({
      name: "storage headroom and aborted transaction retain evidence; orphaned metadata cannot reinitialize over old records",
      result: "PASS",
    });
    const patientBoundary = await page.evaluate(async (scope) => {
      const q = await OfflineQueue.EncryptedQueue.open({
        ...scope,
        deviceId: crypto.randomUUID(),
      });
      await q.initialize("synthetic patient guard unlock", {
        maxTransactions: 2,
        maxAmountCents: "3000",
      });
      let blocked = false;
      try {
        await q.enqueue({
          kind: "sale.draft",
          amountCents: "0",
          data: { nested: { patient_id: crypto.randomUUID() } },
        });
      } catch {
        blocked = true;
      }
      const count = (await q.entries()).length;
      q.close();
      return { blocked, count };
    }, scope);
    assert.equal(patientBoundary.blocked, true);
    assert.equal(patientBoundary.count, 0);
    report.cases.push({
      name: "structured patient identity blocked before browser queue write",
      result: "PASS",
    });
    const clock = await page.evaluate(() => {
      const guard = new OfflineQueue.TrustedClock();
      const initiallyBlocked = !guard.evidence().eligible;
      const wallNow = Date.now;
      const now = wallNow();
      guard.begin(
        {
          id: crypto.randomUUID(),
          version: 1,
          serverNow: new Date(now).toISOString(),
          expiresAt: new Date(now + 900000).toISOString(),
        },
        performance.now(),
      );
      const eligible = guard.evidence().eligible;
      Date.now = () => wallNow() - 60000;
      const changed = guard.evidence();
      Date.now = wallNow;
      const remainsBlocked = !guard.evidence().eligible;
      guard.close();
      const restarted = new OfflineQueue.TrustedClock();
      const restartBlocked = !restarted.evidence().eligible;
      restarted.close();
      return {
        initiallyBlocked,
        eligible,
        changed: changed.reason,
        remainsBlocked,
        restartBlocked,
      };
    });
    assert.equal(clock.initiallyBlocked, true);
    assert.equal(clock.eligible, true);
    assert.equal(clock.changed, "clock_changed");
    assert.equal(clock.remainsBlocked, true);
    assert.equal(clock.restartBlocked, true);
    report.cases.push({
      name: "clock rollback freezes authority; fresh online sample required after new context",
      result: "PASS",
    });
    report.result = "PASS";
  } catch (e) {
    report.result = "FAIL";
    report.error = e.message;
    console.error(e.message);
    process.exitCode = 1;
  } finally {
    if (context) await context.close();
    await new Promise((resolve) => server.close(resolve));
    report.serverStopped = true;
    fs.writeFileSync(
      directory + "/report.json",
      JSON.stringify(report, null, 2),
    );
  }
})();
