// Server-side Node.js client. Never bundle this module or its key into a browser.
const domains = new Set([
  "sales",
  "finance",
  "projects",
  "inventory",
  "hr",
  "attendance",
  "leaves",
  "roster",
  "procurement",
  "manufacturing",
]);
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
const resources = new Set([
  "products",
  "employees",
  "customers",
  "suppliers",
  "quotations",
  "invoices",
  "delivery-orders",
  "payments",
  "purchase-orders",
  "goods-receipts",
  "projects",
  "tasks",
  "approvals",
  "documents",
]);
function allowedPath(path) {
  if (typeof path !== "string") return false;
  const p = path.split("/");
  if (p[0] === "summaries") return p.length === 2 && domains.has(p[1]);
  if (p[0] === "reference")
    return (
      p.length === 2 &&
      [
        "branches",
        "divisions",
        "departments",
        "positions",
        "warehouses",
        "currencies",
      ].includes(p[1])
    );
  if (p[0] === "inventory") return path === "inventory/balances";
  if (p[0] === "manufacturing")
    return (
      ["boms", "work-orders"].includes(p[1]) &&
      (p.length === 2 ||
        (uuid.test(p[2]) &&
          (p.length === 3 || (p.length === 4 && p[3] === "items"))))
    );
  if (!resources.has(p[0])) return false;
  if (p.length === 1) return true;
  if (!uuid.test(p[1])) return false;
  if (p.length === 2) return true;
  if (p.length !== 3) return false;
  return (
    ([
      "quotations",
      "invoices",
      "delivery-orders",
      "purchase-orders",
      "goods-receipts",
    ].includes(p[0]) &&
      p[2] === "items") ||
    (p[0] === "projects" && p[2] === "tasks") ||
    (p[0] === "employees" &&
      ["attendance", "roster", "leave-balances", "leave-requests"].includes(
        p[2],
      ))
  );
}

export class SummaryApiError extends Error {
  constructor(code, status = 0, requestId = null) {
    super(`Summary API: ${code}`);
    this.name = "SummaryApiError";
    this.code = code;
    this.status = status;
    this.requestId = requestId;
  }
}

export function createSummaryClient({
  baseUrl,
  secret,
  timeoutMs = 10000,
  maxRetries = 2,
  maxRetryDelayMs = 60000,
  allowLocalHttp = false,
  fetchImpl = globalThis.fetch,
  sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
}) {
  const base = new URL(baseUrl);
  const local = ["localhost", "127.0.0.1", "[::1]"].includes(base.hostname);
  if (
    base.username ||
    base.password ||
    base.search ||
    base.hash ||
    !(
      base.protocol === "https:" ||
      (allowLocalHttp && local && base.protocol === "http:")
    )
  ) {
    throw new SummaryApiError("invalid_base_url");
  }
  if (!/^erp_[a-f0-9]{24}\.[a-f0-9]{64}$/.test(secret ?? "")) {
    throw new SummaryApiError("invalid_key_format");
  }
  if (
    !Number.isInteger(timeoutMs) ||
    timeoutMs < 1 ||
    timeoutMs > 120000 ||
    !Number.isInteger(maxRetries) ||
    maxRetries < 0 ||
    maxRetries > 5 ||
    !Number.isInteger(maxRetryDelayMs) ||
    maxRetryDelayMs < 0 ||
    maxRetryDelayMs > 120000
  ) {
    throw new SummaryApiError("invalid_retry_configuration");
  }
  base.pathname = base.pathname.replace(/\/$/, "") + "/";
  async function getJSON(path, filters) {
    const url = new URL(path, base);
    for (const [field, value] of Object.entries(filters)) {
      if (
        ![
          "start_date",
          "end_date",
          "limit",
          "cursor",
          "updated_since",
          "search",
          "status",
          "type",
          "is_active",
          "branch_id",
          "division_id",
          "department_id",
          "position_id",
          "employment_status",
          "employment_type",
          "product_id",
          "warehouse_id",
          "inventory_lot_id",
          "workflow_instance_id",
        ].includes(field) ||
        !["string", "number", "boolean"].includes(typeof value)
      ) {
        throw new SummaryApiError("invalid_filters");
      }
      url.searchParams.set(
        field,
        typeof value === "boolean" ? (value ? "1" : "0") : String(value),
      );
    }
    for (let attempt = 0; ; attempt++) {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), timeoutMs);
      let delay;
      try {
        const response = await fetchImpl(url, {
          method: "GET",
          redirect: "error",
          cache: "no-store",
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${secret}`,
          },
          signal: controller.signal,
        });
        const requestId = response.headers.get("x-request-id");
        if (response.ok) {
          const body = await response.json();
          if (
            !body ||
            typeof body !== "object" ||
            !("data" in body) ||
            body.meta?.api_version !== "v1"
          )
            throw new SummaryApiError(
              "invalid_response",
              response.status,
              requestId,
            );
          return body;
        }
        const retryable = [429, 502, 503, 504].includes(response.status);
        const retryAfter = response.headers.get("retry-after");
        delay =
          retryAfter === null
            ? Math.min(1000 * 2 ** attempt, maxRetryDelayMs)
            : /^\d+$/.test(retryAfter)
              ? Number(retryAfter) * 1000
              : Math.max(0, Date.parse(retryAfter) - Date.now());
        if (
          !retryable ||
          attempt >= maxRetries ||
          !Number.isFinite(delay) ||
          delay > maxRetryDelayMs
        ) {
          throw new SummaryApiError(
            `http_${response.status}`,
            response.status,
            requestId,
          );
        }
        await response.body?.cancel();
      } catch (error) {
        if (error instanceof SummaryApiError) throw error;
        // Never propagate fetch errors: URLs, headers or upstream text may contain secrets.
        if (attempt >= maxRetries)
          throw new SummaryApiError(
            controller.signal.aborted ? "timeout" : "transport_error",
          );
        delay = Math.min(1000 * 2 ** attempt, maxRetryDelayMs);
      } finally {
        clearTimeout(timer);
      }
      await sleep(delay);
    }
  }
  return {
    async getSummary(domain, filters = {}) {
      if (!domains.has(domain)) throw new SummaryApiError("invalid_domain");
      for (const [field, value] of Object.entries(filters)) {
        if (
          !["start_date", "end_date"].includes(field) ||
          typeof value !== "string" ||
          !/^\d{4}-\d{2}-\d{2}$/.test(value)
        )
          throw new SummaryApiError("invalid_filters");
      }
      return getJSON(domain, filters);
    },
    async getResource(path, filters = {}) {
      if (!base.pathname.endsWith("/api/v1/") || !allowedPath(path))
        throw new SummaryApiError("invalid_resource_path");
      return getJSON(path, filters);
    },
  };
}

export function createExternalReadClient(options) {
  const client = createSummaryClient(options);
  return { get: (path, filters = {}) => client.getResource(path, filters) };
}
