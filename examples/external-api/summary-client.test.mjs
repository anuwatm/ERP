import { test } from "node:test";
import assert from "node:assert/strict";
import {
  createSummaryClient,
  createExternalReadClient,
} from "./summary-client.mjs";

const secret = `erp_${"a".repeat(24)}.${"b".repeat(64)}`;
const options = {
  baseUrl: "https://erp.example.test/ERP/api/v1/summaries",
  secret,
};
const ok = () => Response.json({ data: {}, meta: { api_version: "v1" } });

test("preserves subdirectory and sends secret only as Authorization; blocks redirects", async () => {
  const client = createSummaryClient({
    ...options,
    fetchImpl: async (url, init) => {
      assert.equal(url.pathname, "/ERP/api/v1/summaries/projects");
      assert.equal(url.searchParams.get("start_date"), "2026-10-01");
      assert.ok(!url.href.includes(secret));
      assert.equal(init.headers.Authorization, `Bearer ${secret}`);
      assert.equal(init.redirect, "error");
      return ok();
    },
  });
  await client.getSummary("projects", { start_date: "2026-10-01" });
});

test("429 waits Retry-After and retries boundedly", async () => {
  let calls = 0;
  const waits = [];
  const client = createSummaryClient({
    ...options,
    sleep: async (ms) => waits.push(ms),
    fetchImpl: async () =>
      ++calls === 1
        ? new Response("", { status: 429, headers: { "retry-after": "2" } })
        : ok(),
  });
  await client.getSummary("sales");
  assert.equal(calls, 2);
  assert.deepEqual(waits, [2000]);
});

test("long Retry-After stops instead of retrying too early", async () => {
  let calls = 0;
  const client = createSummaryClient({
    ...options,
    fetchImpl: async () => {
      calls++;
      return new Response("", {
        status: 429,
        headers: { "retry-after": "3600" },
      });
    },
  });
  await assert.rejects(client.getSummary("sales"), { code: "http_429" });
  assert.equal(calls, 1);
});

test("401/403/422 are terminal and never expose upstream response", async () => {
  for (const status of [401, 403, 422]) {
    let calls = 0;
    const client = createSummaryClient({
      ...options,
      fetchImpl: async () => {
        calls++;
        return new Response(secret, {
          status,
          headers: { "x-request-id": "request-1" },
        });
      },
    });
    await assert.rejects(
      client.getSummary("finance"),
      (error) =>
        error.status === status &&
        error.requestId === "request-1" &&
        !error.message.includes(secret),
    );
    assert.equal(calls, 1);
  }
});

test("transport retries stop and redact original errors", async () => {
  let calls = 0;
  const client = createSummaryClient({
    ...options,
    sleep: async () => {},
    fetchImpl: async () => {
      calls++;
      throw new Error(secret);
    },
  });
  await assert.rejects(client.getSummary("sales"), {
    message: "Summary API: transport_error",
  });
  assert.equal(calls, 3);
});

test("timeout aborts pending fetch", async () => {
  const client = createSummaryClient({
    ...options,
    timeoutMs: 5,
    maxRetries: 0,
    fetchImpl: async (_, { signal }) =>
      new Promise((resolve, reject) => {
        signal.addEventListener("abort", () => reject(new Error(secret)), {
          once: true,
        });
      }),
  });
  await assert.rejects(client.getSummary("sales"), { code: "timeout" });
});

test("rejects insecure URLs, credentials in URL, query strings and unsafe filters", async () => {
  for (const baseUrl of [
    "http://erp.example.test",
    "https://u:p@erp.example.test",
    "https://erp.example.test?key=x",
  ]) {
    assert.throws(() => createSummaryClient({ ...options, baseUrl }), {
      code: "invalid_base_url",
    });
  }
  assert.doesNotThrow(() =>
    createSummaryClient({
      ...options,
      baseUrl: "http://127.0.0.1:8111/api/v1/summaries",
      allowLocalHttp: true,
    }),
  );
  const client = createSummaryClient(options);
  await assert.rejects(client.getSummary("../admin"), {
    code: "invalid_domain",
  });
  await assert.rejects(client.getSummary("sales", { org_id: "foreign" }), {
    code: "invalid_filters",
  });
});

test("rejects incompatible response envelope", async () => {
  const client = createSummaryClient({
    ...options,
    fetchImpl: async () => Response.json({ secret }),
  });
  await assert.rejects(client.getSummary("sales"), {
    code: "invalid_response",
  });
});

test("resource client permits only fixed read paths and preserves cursor and subdirectory", async () => {
  const paths = [];
  const client = createExternalReadClient({
    ...options,
    baseUrl: "https://erp.example.test/ERP/api/v1",
    fetchImpl: async (url, init) => {
      paths.push(url.pathname);
      assert.equal(init.method, "GET");
      assert.equal(url.searchParams.get("cursor"), "opaque+/=");
      return ok();
    },
  });
  await client.get("employees", { limit: 50, cursor: "opaque+/=" });
  assert.deepEqual(paths, ["/ERP/api/v1/employees"]);
  for (const path of [
    "https://evil.example.test",
    "//evil.example.test",
    "../admin",
    "employees?org_id=x",
    "payroll",
    "bank-accounts",
    "tasks/new",
    "documents/../../secret",
  ]) {
    await assert.rejects(client.get(path), { code: "invalid_resource_path" });
  }
  assert.equal(paths.length, 1);
});
