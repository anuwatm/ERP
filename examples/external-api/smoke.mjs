// Read-only smoke test against a target explicitly configured by the operator.
import { createSummaryClient, SummaryApiError } from "./summary-client.mjs";

try {
  const client = createSummaryClient({
    baseUrl: process.env.ERP_SUMMARY_BASE_URL,
    secret: process.env.ERP_API_SECRET,
    allowLocalHttp: process.env.ERP_ALLOW_LOCAL_HTTP === "1",
  });
  // Choose only domains granted to this key. Defaults to sales (one request).
  const domains = (process.env.ERP_SUMMARY_DOMAINS ?? "sales").split(",");
  if (
    !domains.length ||
    domains.length > 10 ||
    new Set(domains).size !== domains.length ||
    domains.some(
      (domain) =>
        ![
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
        ].includes(domain),
    )
  ) {
    throw new SummaryApiError("invalid_domain");
  }
  for (const domain of domains) {
    const result = await client.getSummary(domain);
    if (
      !["organization", "credential_hr_policy"].includes(
        result.meta.visibility,
      ) ||
      result.meta.freshness !== "live"
    ) {
      throw new SummaryApiError("invalid_metadata");
    }
    console.log(JSON.stringify({ domain, ok: true }));
  }
} catch (error) {
  // Do not print upstream errors, URLs, environment variables or business payloads.
  console.error(
    JSON.stringify({
      ok: false,
      code:
        error instanceof SummaryApiError ? error.code : "invalid_configuration",
      status: error instanceof SummaryApiError ? error.status : 0,
    }),
  );
  process.exitCode = 1;
}
