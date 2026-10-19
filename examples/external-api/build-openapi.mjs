// Rebuild the read-only API contract from explicitly reviewed field allowlists.
import fs from "node:fs";
import { fileURLToPath } from "node:url";
const root = new URL("../../", import.meta.url);
const spec = JSON.parse(
  fs.readFileSync(
    new URL("docs/openapi/external-summary-v1.json", root),
    "utf8",
  ),
);
spec.info.title = "ERP External Read-only API";
spec.info.version = "1.2.0";
spec.info.description +=
  " P1–P6 read resources. No payroll, bank, identity or business write APIs. New scopes are opt-in. Party/HR/document policies apply; full reconciliation is required for deletions and access changes.";
const schemas = spec.components.schemas;
const ref = (name) => ({ $ref: `#/components/schemas/${name}` });
const array = (items) => ({ type: "array", items });
const integer = { type: "integer" };
const bool = { type: "boolean" };
const text = { type: ["string", "null"] };
const decimal = (places) => ({
  type: "string",
  pattern: `^-?[0-9]+\\.[0-9]{${places}}$`,
});
const dateFields = new Set([
  "work_date",
  "schedule_date",
  "start_date",
  "due_date",
  "issue_date",
  "valid_until",
  "order_date",
  "expected_date",
  "received_date",
  "delivery_date",
  "payment_date",
  "completed_date",
  "started_on",
  "probation_ends_on",
  "resigned_on",
  "starts_on",
  "ends_on",
]);
const boolFields = new Set([
  "is_active",
  "track_inventory",
  "is_reconciled",
  "is_absent",
  "is_day_off",
  "qc_passed",
]);
const numberFields = new Set([
  "progress_percent",
  "step_no",
  "sequence",
  "leave_year",
  "version_no",
  "size_bytes",
  "decimal_places",
]);
const moneyFields = new Set([
  "price",
  "cost",
  "subtotal",
  "discount_amount",
  "tax_amount",
  "total",
  "paid_amount",
  "balance_due",
  "amount",
  "unit_price",
  "unit_cost",
  "line_total",
  "total_cost",
  "material_cost",
  "labor_cost",
  "overhead_cost",
]);
function field(name) {
  if (name === "id" || name.endsWith("_id"))
    return {
      type: name === "id" ? "string" : ["string", "null"],
      format: "uuid",
    };
  if (dateFields.has(name)) return { type: ["string", "null"], format: "date" };
  if (name.endsWith("_at"))
    return { type: ["string", "null"], format: "date-time" };
  if (boolFields.has(name))
    return { type: name === "qc_passed" ? ["boolean", "null"] : "boolean" };
  if (numberFields.has(name) || name.endsWith("_minutes")) return integer;
  if (name.includes("quantity") || name === "on_hand") return decimal(4);
  if (
    moneyFields.has(name) ||
    name.endsWith("_days") ||
    name === "waste_percent" ||
    name === "tax_rate"
  )
    return decimal(2);
  return text;
}
function object(fields, extras = {}, optional = []) {
  return {
    type: "object",
    additionalProperties: false,
    properties: {
      ...Object.fromEntries(fields.map((name) => [name, field(name)])),
      ...extras,
    },
    required: [...fields, ...Object.keys(extras)].filter(
      (name) => !optional.includes(name),
    ),
  };
}
schemas.ReadMeta = {
  type: "object",
  required: ["api_version", "request_id"],
  properties: {
    api_version: { const: "v1" },
    request_id: { type: "string", format: "uuid" },
    next_cursor: text,
    generated_at: { type: "string", format: "date-time" },
    limit: { type: "integer", minimum: 1, maximum: 100 },
    consistency: { const: "live" },
    reconciliation_required: bool,
    delta_supported: bool,
    sync_watermark: { type: "string", format: "date-time" },
    cursor_expires_at: { type: "string", format: "date-time" },
  },
  additionalProperties: true,
};
schemas.ReadError = object(["request_id"], {
  code: { type: "string" },
  message: { type: "string" },
});
schemas.ReadTombstone = object(["id", "deleted_at", "updated_at"]);
const envelope = (data) => ({
  type: "object",
  required: ["data", "meta"],
  additionalProperties: false,
  properties: { data, meta: ref("ReadMeta") },
});
const query = (name, schema) => ({
  name,
  in: "query",
  required: false,
  schema,
});
const pages = [
  query("limit", { type: "integer", minimum: 1, maximum: 100, default: 50 }),
  query("cursor", { type: "string", maxLength: 4096 }),
];
const delta = [
  ...pages,
  query("updated_since", {
    type: "string",
    pattern: "^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$",
  }),
];
const period = ["start_date", "end_date"].map((name) =>
  query(name, { type: "string", format: "date" }),
);
const idParam = {
  name: "id",
  in: "path",
  required: true,
  schema: { type: "string", format: "uuid" },
};
const json = (schema) => ({ content: { "application/json": { schema } } });
function add(path, scope, data, params = [], description = "") {
  const fullPath = `/api/v1/${path}`;
  const responses = {
    200: {
      description:
        "Read-only response; no-store. Live consistency; no implicit historical snapshot.",
      ...json(envelope(data)),
    },
  };
  for (const status of [401, 403, 404, 405, 409, 422, 426, 429, 500])
    responses[status] = {
      description: `HTTP ${status}; sanitized error. Retry-After applies to 429.`,
      ...json(ref("ReadError")),
    };
  spec.paths[fullPath] = {
    get: {
      operationId: "read_" + path.replace(/[{}\/\-]/g, "_"),
      summary: `Read ${path}`,
      description: `${description} Required scope: ${scope}. Unknown filters/body are rejected. No org_id/user_id impersonation.`,
      security: [{ integrationBearer: [] }],
      "x-required-scopes": scope.split(" + "),
      parameters: [...(path.includes("{id}") ? [idParam] : []), ...params],
      responses,
    },
  };
}
function resource(
  path,
  schema,
  scope,
  { params = delta, tombstones = true, detail = true } = {},
) {
  add(
    path,
    scope,
    array(
      tombstones ? { oneOf: [ref(schema), ref("ReadTombstone")] } : ref(schema),
    ),
    params,
    "Default50/max100. Authenticated cursor expires after30m and is bound to key/policy/path/filters. Full reconciliation is required.",
  );
  if (detail) add(`${path}/{id}`, scope, ref(schema));
}
schemas.ProductRead = object(
  [
    "id",
    "sku",
    "name",
    "type",
    "category",
    "unit",
    "is_active",
    "track_inventory",
    "updated_at",
  ],
  { price: decimal(2), cost: decimal(2) },
  ["price", "cost"],
);
resource("products", "ProductRead", "products:read", {
  params: [
    ...delta,
    query("search", { type: "string", maxLength: 100 }),
    query("type", { enum: ["product", "service", "package"] }),
    query("is_active", { enum: [0, 1] }),
  ],
});
for (const [name, fields, scope, tombstones] of [
  [
    "branches",
    ["id", "code", "name", "status", "updated_at"],
    "reference:organization:read",
    true,
  ],
  [
    "divisions",
    ["id", "code", "name", "status", "branch_id", "updated_at"],
    "reference:organization:read",
    true,
  ],
  [
    "departments",
    ["id", "code", "name", "status", "branch_id", "division_id", "updated_at"],
    "reference:organization:read",
    true,
  ],
  [
    "positions",
    ["id", "code", "name", "status", "department_id", "updated_at"],
    "reference:organization:read",
    true,
  ],
  [
    "warehouses",
    ["id", "code", "name", "status", "branch_id", "updated_at"],
    "inventory:read",
    false,
  ],
  [
    "currencies",
    ["id", "code", "name", "decimal_places", "status", "updated_at"],
    "reference:currencies:read",
    false,
  ],
]) {
  schemas[`Reference_${name}`] = object(fields);
  resource(`reference/${name}`, `Reference_${name}`, scope, {
    tombstones,
    detail: false,
  });
}
schemas.InventoryBalanceRead = object(
  [
    "product_id",
    "warehouse_id",
    "warehouse_bin_id",
    "inventory_lot_id",
    "lot_no",
    "on_hand",
  ],
  { expires_at: { type: ["string", "null"], format: "date" } },
);
add(
  "inventory/balances",
  "inventory:read",
  array(ref("InventoryBalanceRead")),
  [
    ...pages,
    ...["product_id", "warehouse_id", "inventory_lot_id"].map((name) =>
      query(name, { type: "string", format: "uuid" }),
    ),
  ],
  "On-hand only. No reservation/available/valuation. Legacy warehouse=null is unassigned.",
);
const countGroup = object([], { status: text, count: integer });
const qtyGroup = object([], {
  unit: text,
  lines_count: integer,
  ordered_quantity: decimal(4),
  received_quantity: decimal(4),
  outstanding_quantity: decimal(4),
});
add(
  "summaries/inventory",
  "summary:inventory:read",
  object([], {
    snapshot: object(["as_of"], {
      active_tracked_products: integer,
      low_stock_products: integer,
      negative_stock_products: integer,
    }),
  }),
  [],
  "Current snapshot; reorder_point>0 and on_hand<reorder_point. Includes zero-stock tracked active products.",
);
schemas.EmployeeRead = object([
  "id",
  "employee_code",
  "first_name",
  "last_name",
  "nickname",
  "branch_id",
  "division_id",
  "department_id",
  "position_id",
  "manager_id",
  "employment_type",
  "employment_status",
  "started_on",
  "probation_ends_on",
  "resigned_on",
  "updated_at",
]);
resource("employees", "EmployeeRead", "employees:read", {
  tombstones: false,
  params: [
    ...delta,
    ...[
      "search",
      "employment_status",
      "employment_type",
      "branch_id",
      "division_id",
      "department_id",
      "position_id",
    ].map((name) => query(name, { type: "string", maxLength: 100 })),
  ],
});
schemas.AttendanceRead = object([
  "id",
  "work_date",
  "clock_in_at",
  "clock_out_at",
  "late_minutes",
  "early_leave_minutes",
  "worked_minutes",
  "ot_normal_minutes",
  "ot_holiday_minutes",
  "ot_special_minutes",
  "is_reconciled",
  "is_absent",
  "updated_at",
]);
schemas.RosterRead = object(
  ["id", "schedule_date", "is_day_off", "updated_at"],
  {
    shift: {
      anyOf: [
        object(["id", "starts_at", "ends_at", "break_minutes"]),
        { type: "null" },
      ],
    },
  },
);
schemas.LeaveRequestRead = object([
  "id",
  "starts_on",
  "ends_on",
  "total_days",
  "status",
  "updated_at",
]);
schemas.LeaveBalanceRead = object([
  "id",
  "leave_type_id",
  "leave_year",
  "opening_days",
  "accrued_days",
  "carry_over_days",
  "used_days",
  "remaining_days",
  "updated_at",
  "as_of",
]);
for (const [part, scope, schema] of [
  ["attendance", "attendance", "AttendanceRead"],
  ["roster", "roster", "RosterRead"],
  ["leave-requests", "leaves", "LeaveRequestRead"],
  ["leave-balances", "leaves", "LeaveBalanceRead"],
]) {
  add(
    `employees/{id}/${part}`,
    `employees:${scope}:read`,
    array(ref(schema)),
    part === "leave-balances" ? pages : [...pages, ...period],
    "HR branch policy applies. Non-User leave endpoints return409 unsupported_employee_coverage; current balances are not historical.",
  );
}
// Summary shapes remain explicit; no model serialization, free-text reasons or personal contacts.
const unitCounts = object([], {
  truncated: bool,
  groups: array(
    object(["id"], {
      id: { type: ["string", "null"], format: "uuid" },
      count: { type: ["integer", "null"] },
      suppressed: bool,
    }),
  ),
});
add(
  "summaries/hr",
  "summary:hr:read",
  object([], {
    period: object([], {
      employees_started: integer,
      employees_departed: integer,
    }),
    snapshot: object([], {
      total_employees: integer,
      active_employees: integer,
      by_status_and_type: array(
        object(["employment_status", "employment_type"], { count: integer }),
      ),
      probation_ends_within_30_days: integer,
      small_group_threshold: integer,
      org_units: object(
        [],
        Object.fromEntries(
          ["branch_id", "division_id", "department_id", "position_id"].map(
            (name) => [name, unitCounts],
          ),
        ),
      ),
    }),
  }),
  period,
  "Employee basis, HR policy, current status/type. Small org-unit groups suppressed below5.",
);
const attendanceCounts = [
  "employee_workdays",
  "distinct_employees",
  "worked_minutes",
  "late_minutes",
  "early_leave_minutes",
  "ot_normal_minutes",
  "ot_holiday_minutes",
  "ot_special_minutes",
];
for (const name of [
  "present_workdays",
  "late_workdays",
  "early_leave_workdays",
  "confirmed_absent_workdays",
  "pending_workdays",
  "incomplete_workdays",
])
  attendanceCounts.push(name, `${name}_employees`);
add(
  "summaries/attendance",
  "summary:attendance:read",
  object([], {
    period: object(
      [],
      Object.fromEntries(attendanceCounts.map((name) => [name, integer])),
    ),
    coverage: object([], {
      basis: text,
      legacy_user_bridge: bool,
      missing_punch_is_absence: bool,
      minutes_basis: text,
    }),
  }),
  period,
  "Canonical employee/work_date, reconciled minutes only. Never infer absence from missing punch.",
);
add(
  "summaries/leaves",
  "summary:leaves:read",
  object([], {
    period: object([], {
      requests_overlapping_by_status: array(countGroup),
      approved_distinct_employees: integer,
      approved_leave_employee_workdays: decimal(2),
    }),
    coverage: object([], {
      linked_employees: integer,
      unsupported_non_user_employees: integer,
      day_policy: text,
      type_breakdown: text,
      overlap_deduplication: text,
    }),
  }),
  period,
  "Intersection business days excluding weekends/org holidays; distinct employee/date. No leave-type breakdown.",
);
add(
  "summaries/roster",
  "summary:roster:read",
  object([], {
    period: object([], {
      allocated_days: integer,
      day_off_allocations: integer,
      scheduled_employees: integer,
      eligible_employees: integer,
      employees_without_any_schedule: integer,
      missing_schedule_workdays: { type: "null" },
      department_allocations: array(
        object(["department_id"], {
          allocated_days: { type: ["integer", "null"] },
          scheduled_employees: { type: ["integer", "null"] },
          suppressed: bool,
        }),
      ),
      departments_truncated: bool,
    }),
    coverage: object([], {
      eligibility_basis: text,
      missing_workdays_reason: text,
    }),
  }),
  period,
  "Unknown required-workday coverage remains null, never inferred as absence.",
);
const documents = {
  customers: [
    "id",
    "customer_code",
    "company_name",
    "customer_type",
    "status",
    "updated_at",
  ],
  suppliers: ["id", "supplier_code", "name", "status", "updated_at"],
  quotations: [
    "id",
    "quotation_no",
    "customer_id",
    "status",
    "issue_date",
    "valid_until",
    "subtotal",
    "discount_amount",
    "tax_amount",
    "total",
    "currency",
    "updated_at",
  ],
  invoices: [
    "id",
    "invoice_no",
    "customer_id",
    "status",
    "issue_date",
    "due_date",
    "subtotal",
    "discount_amount",
    "tax_amount",
    "total",
    "paid_amount",
    "balance_due",
    "currency",
    "updated_at",
  ],
  "delivery-orders": [
    "id",
    "do_no",
    "invoice_id",
    "status",
    "delivery_date",
    "delivered_at",
    "updated_at",
  ],
  payments: [
    "id",
    "invoice_id",
    "entry_type",
    "reversal_of_payment_id",
    "payment_date",
    "amount",
    "currency",
    "updated_at",
  ],
  "purchase-orders": [
    "id",
    "po_no",
    "supplier_id",
    "status",
    "order_date",
    "expected_date",
    "subtotal",
    "discount_amount",
    "tax_amount",
    "total",
    "currency",
    "updated_at",
  ],
  "goods-receipts": [
    "id",
    "grn_no",
    "purchase_order_id",
    "received_date",
    "status",
    "currency",
    "updated_at",
  ],
  projects: [
    "id",
    "project_code",
    "name",
    "status",
    "start_date",
    "due_date",
    "progress_percent",
    "updated_at",
  ],
  tasks: [
    "id",
    "project_id",
    "title",
    "status",
    "priority",
    "due_date",
    "completed_at",
    "updated_at",
  ],
};
const lineFields = [
  "id",
  "product_id",
  "quantity",
  "unit",
  "unit_price",
  "discount_amount",
  "tax_rate",
  "line_total",
  "updated_at",
];
for (const [path, fields] of Object.entries(documents)) {
  const name = `Business_${path.replaceAll("-", "_")}`;
  schemas[name] = object(fields);
  resource(path, name, `${path}:read`, {
    tombstones: !["payments", "delivery-orders", "goods-receipts"].includes(
      path,
    ),
    params: fields.includes("status")
      ? [...delta, query("status", { type: "string", maxLength: 30 })]
      : delta,
  });
  if (
    [
      "quotations",
      "invoices",
      "delivery-orders",
      "purchase-orders",
      "goods-receipts",
    ].includes(path)
  ) {
    const item =
      path === "delivery-orders"
        ? ["id", "product_id", "quantity", "unit", "updated_at"]
        : path === "goods-receipts"
          ? [
              "id",
              "purchase_order_item_id",
              "product_id",
              "quantity",
              "unit",
              "updated_at",
            ]
          : lineFields;
    schemas[`${name}_item`] = object(
      item,
      path === "purchase-orders"
        ? { received_quantity: decimal(4), outstanding_quantity: decimal(4) }
        : {},
    );
    schemas[`${name}_detail`] = object(fields, {
      items: { ...array(ref(`${name}_item`)), maxItems: 100 },
      items_truncated: bool,
    });
    add(`${path}/{id}`, `${path}:read`, ref(`${name}_detail`));
    add(
      `${path}/{id}/items`,
      `${path}:read`,
      array(ref(`${name}_item`)),
      path === "purchase-orders" ? pages : delta,
    );
  }
}
add(
  "projects/{id}/tasks",
  "projects:read + tasks:read",
  array(ref("Business_tasks")),
  delta,
  "Parent AND task row visibility apply; no work-hours source.",
);
add(
  "summaries/procurement",
  "summary:procurement:read",
  object([], {
    period: object([], {
      orders_by_status_and_currency: array(
        object(["status", "currency"], { count: integer, amount: decimal(2) }),
      ),
    }),
    snapshot: object(["as_of"], {
      open_order_quantities_by_unit: array(qtyGroup),
      units_truncated: bool,
    }),
  }),
  period,
  "Posted GRN quantities with matching PO/item/org/product/unit, no raw quantities summed across units.",
);
schemas.ApprovalRead = object(
  ["id", "workflow_instance_id", "step_no", "status", "acted_at", "updated_at"],
  { subject: object(["subject_type", "subject_id", "status"]) },
);
resource("approvals", "ApprovalRead", "approvals:read", {
  tombstones: false,
  params: [
    ...pages,
    query("status", {
      enum: ["queued", "pending", "approved", "rejected", "revision_requested"],
    }),
    query("workflow_instance_id", { type: "string", format: "uuid" }),
  ],
});
schemas.DocumentRead = object(
  ["id", "document_no", "title", "category_id", "status", "updated_at"],
  {
    expires_at: { type: ["string", "null"], format: "date" },
    current_version: object([
      "version_no",
      "mime_type",
      "size_bytes",
      "scan_status",
    ]),
  },
);
resource("documents", "DocumentRead", "documents:read", { tombstones: false });
add(
  "documents/{id}/download",
  "documents:read + documents:download",
  { type: "string", format: "binary" },
  [],
  "Private current-version stream. Explicit document allowlist, issuer/parent ACL and clean scan required. No confidential/payroll/bank documents.",
);
spec.paths["/api/v1/documents/{id}/download"].get.responses[200] = {
  description:
    "Attachment stream, no public URL/storage path, no-store and nosniff.",
  content: { "*/*": { schema: { type: "string", format: "binary" } } },
};
const manufacturing = {
  boms: [
    "id",
    "bom_no",
    "name",
    "product_id",
    "version",
    "quantity",
    "unit",
    "is_active",
    "updated_at",
  ],
  "work-orders": [
    "id",
    "wo_no",
    "bill_of_materials_id",
    "product_id",
    "status",
    "planned_quantity",
    "actual_quantity",
    "scrap_quantity",
    "start_date",
    "due_date",
    "completed_date",
    "qc_passed",
    "updated_at",
  ],
};
for (const [part, fields] of Object.entries(manufacturing)) {
  const name = `Manufacturing_${part.replaceAll("-", "_")}`;
  const costFields =
    part === "boms"
      ? ["labor_cost", "overhead_cost", "total_cost"]
      : [
          "material_cost",
          "labor_cost",
          "overhead_cost",
          "total_cost",
          "unit_cost",
        ];
  const costs = object(costFields, {
    currency: { type: "null" },
    currency_status: { const: "not_recorded_on_source" },
  });
  schemas[name] = object(fields, { costs }, ["costs"]);
  resource(`manufacturing/${part}`, name, `manufacturing:${part}:read`, {
    params: [
      ...delta,
      query(
        part === "boms" ? "is_active" : "status",
        part === "boms"
          ? { enum: [0, 1] }
          : {
              enum: [
                "draft",
                "planned",
                "in_progress",
                "quality_check",
                "completed",
                "cancelled",
              ],
            },
      ),
    ],
  });
  const itemFields =
    part === "boms"
      ? [
          "id",
          "product_id",
          "sub_bom_id",
          "quantity",
          "unit",
          "waste_percent",
          "sequence",
          "updated_at",
        ]
      : [
          "id",
          "product_id",
          "planned_quantity",
          "issued_quantity",
          "unit",
          "updated_at",
        ];
  schemas[`${name}_item`] = object(
    itemFields,
    {
      unit_cost: decimal(2),
      total_cost: decimal(2),
      cost_currency_status: { const: "not_recorded_on_source" },
    },
    ["unit_cost", "total_cost", "cost_currency_status"],
  );
  add(
    `manufacturing/${part}/{id}/items`,
    part === "boms"
      ? "manufacturing:boms:read + manufacturing:formulas:read"
      : "manufacturing:work-orders:read",
    array(ref(`${name}_item`)),
    delta,
  );
}
add(
  "summaries/manufacturing",
  "summary:manufacturing:read",
  object([], {
    period: object([], {
      work_orders_created_by_current_status: array(countGroup),
    }),
    snapshot: object(["as_of"], {
      active_boms: integer,
      work_orders_by_status: array(countGroup),
      production_quantities_by_unit: array(
        object([
          "unit",
          "planned_quantity",
          "actual_quantity",
          "scrap_quantity",
        ]),
      ),
      units_truncated: bool,
    }),
  }),
  period,
  "Stored manufacturing state. Costs/formulas are separate opt-in scopes.",
);
schemas.IntegrationWebhookEvent = object(["id", "occurred_at", "resource_id"], {
  schema_version: { const: 1 },
  event_type: {
    enum: [
      "invoice.payment.status.changed",
      "stock.balance.changed",
      "task.status.changed",
      "approval.status.changed",
    ],
  },
  resource_type: { enum: ["invoice", "product", "task", "workflow_instance"] },
});
spec.webhooks = {
  resourceChanged: {
    post: {
      description:
        "OUTBOUND callback on receiver, NOT an ERP write API. At-least-once; dedupe id, verify HMAC(timestamp + dot + exact bytes), enforce5m replay window then fetch current scoped data. Live dispatch disabled by default.",
      parameters: [
        {
          name: "X-ERP-Signature",
          in: "header",
          required: true,
          schema: { type: "string", pattern: "^t=[0-9]+,v1=[a-f0-9]{64}$" },
        },
      ],
      requestBody: { required: true, ...json(ref("IntegrationWebhookEvent")) },
      responses: { 204: { description: "Receiver accepted event." } },
    },
  },
};
const target = new URL("docs/openapi/external-read-v1.json", root);
fs.writeFileSync(target, JSON.stringify(spec, null, 2) + "\n");
console.log(
  `Wrote ${Object.keys(spec.paths).length} GET paths to ${fileURLToPath(target)}`,
);
