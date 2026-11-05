export type QueueScope = {
  instanceId: string;
  branchId: string;
  staffId: string;
  terminalId: string;
  deviceId: string;
};
export type QueueStatus =
  "pending" | "syncing" | "synced" | "failed" | "review";
export type QueueEntry = {
  id: string;
  kind: "sale.draft" | "restaurant.order.draft" | "delivery.request";
  status: QueueStatus;
  clientCreatedAt: string;
  amountCents: string;
  data: Record<string, unknown>;
  acknowledgement?: {
    inbox_id: string;
    client_transaction_id: string;
    status: string;
    sale_id: string | null;
    financial_posted: boolean;
  };
  chain?: {
    sequence: number;
    previous: string;
    hash: string;
  };
};
export type CashPermit = {
  id: string;
  device_id: string;
  staff_id: string;
  branch_id: string;
  terminal_id: string;
  instance_id: string;
  expires_at: string;
  policy: {
    version: number;
    max_offline_transactions: number;
    max_offline_amount: string;
  };
  items: {
    product_id: string;
    name: string;
    unit_price: string;
    tax_rate: string;
    quantity: string;
  }[];
  slots: {
    id: string;
    ordinal: number;
  }[];
};
type State = {
  revision: number;
  count: number;
  amountCents: string;
  maxTransactions: number;
  maxAmountCents: string;
  chainHead?: string;
  permit?: CashPermit;
  used?: Record<string, string>;
  slotCount?: number;
  cashCents?: string;
};
type Vault = {
  id: string;
  salt: string;
  revision: number;
  iv: string;
  ciphertext: string;
};
type Cipher = {
  id: string;
  scope: string;
  iv: string;
  ciphertext: string;
};
const encode = (value: string) =>
  new TextEncoder().encode(value).buffer as ArrayBuffer;
const b64 = (bytes: ArrayBuffer) => {
  const data = new Uint8Array(bytes);
  let text = "";
  for (let i = 0; i < data.length; i += 8192)
    text += String.fromCharCode(...data.subarray(i, i + 8192));
  return btoa(text);
};
const unb64 = (value: string) =>
  Uint8Array.from(atob(value), (c) => c.charCodeAt(0)).buffer as ArrayBuffer;
const request = <T>(value: IDBRequest<T>) =>
  new Promise<T>((resolve, reject) => {
    value.onsuccess = () => resolve(value.result);
    value.onerror = () => reject(value.error);
  });
const completed = (tx: IDBTransaction) => {
  const result = new Promise<void>((resolve, reject) => {
    tx.oncomplete = () => resolve();
    tx.onabort = () =>
      reject(tx.error || new Error("Queue transaction aborted"));
    tx.onerror = () => reject(tx.error || new Error("Queue write failed"));
  });
  // A request may reject before the caller reaches await oncomplete. Keep that abort handled.
  void result.catch(() => {});
  return result;
};
/** Storage primitive only: queue durability never grants quota, sale or tax authority. */
export class EncryptedQueue {
  private key: CryptoKey | null = null;
  private constructor(
    private db: IDBDatabase,
    private scope: string,
  ) {
    window.addEventListener("pagehide", () => this.lock());
  }
  static async open(scope: QueueScope): Promise<EncryptedQueue> {
    if (!window.isSecureContext || !crypto.subtle || !indexedDB)
      throw new Error("Secure browser storage unavailable");
    const ordered = [
      scope.instanceId,
      scope.branchId,
      scope.staffId,
      scope.terminalId,
      scope.deviceId,
    ];
    if (
      ordered.some(
        (s) =>
          !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(
            s,
          ),
      )
    )
      throw new Error("Invalid queue scope");
    const digest = await crypto.subtle.digest(
      "SHA-256",
      encode(JSON.stringify(ordered.map((s) => s.toLowerCase()))),
    );
    const id = [...new Uint8Array(digest)]
      .map((b) => b.toString(16).padStart(2, "0"))
      .join("");
    const opening = indexedDB.open("company-pos-offline-v1", 1);
    opening.onupgradeneeded = () => {
      opening.result.createObjectStore("vaults", { keyPath: "id" });
      const records = opening.result.createObjectStore("records", {
        keyPath: "id",
      });
      records.createIndex("scope", "scope");
    };
    const db = await request(opening);
    db.onversionchange = () => db.close();
    return new EncryptedQueue(db, id);
  }
  static async storage(): Promise<{
    persistent: boolean;
    freeBytes: number;
  }> {
    if (!navigator.storage?.estimate)
      throw new Error("Cannot verify browser storage headroom");
    const persistent = navigator.storage.persist
      ? await navigator.storage.persist().catch(() => false)
      : false;
    const { quota, usage } = await navigator.storage.estimate();
    if (quota === undefined || usage === undefined)
      throw new Error("Browser storage estimate unavailable");
    return { persistent, freeBytes: quota - usage };
  }
  private async derive(passphrase: string, salt: string): Promise<CryptoKey> {
    if (
      Array.from(passphrase).length < 12 ||
      passphrase.length > 128 ||
      !passphrase.trim()
    )
      throw new Error("Unlock passphrase requires 12–128 characters");
    const material = await crypto.subtle.importKey(
      "raw",
      encode(passphrase),
      "PBKDF2",
      false,
      ["deriveKey"],
    );
    return crypto.subtle.deriveKey(
      {
        name: "PBKDF2",
        hash: "SHA-256",
        salt: unb64(salt),
        iterations: 600000,
      },
      material,
      { name: "AES-GCM", length: 256 },
      false,
      ["encrypt", "decrypt"],
    );
  }
  private async seal(
    value: unknown,
    id: string,
    key = this.key,
  ): Promise<{
    iv: string;
    ciphertext: string;
  }> {
    if (!key) throw new Error("Queue locked");
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ciphertext = await crypto.subtle.encrypt(
      {
        name: "AES-GCM",
        iv,
        additionalData: encode(this.scope + "|" + id),
        tagLength: 128,
      },
      key,
      encode(JSON.stringify(value)),
    );
    return { iv: b64(iv.buffer), ciphertext: b64(ciphertext) };
  }
  private async unseal<T>(
    cipher: {
      iv: string;
      ciphertext: string;
    },
    id: string,
    key = this.key,
  ): Promise<T> {
    if (!key) throw new Error("Queue locked");
    const bytes = await crypto.subtle.decrypt(
      {
        name: "AES-GCM",
        iv: unb64(cipher.iv),
        additionalData: encode(this.scope + "|" + id),
        tagLength: 128,
      },
      key,
      unb64(cipher.ciphertext),
    );
    return JSON.parse(new TextDecoder().decode(bytes)) as T;
  }
  private async vault(): Promise<Vault | undefined> {
    return request(
      this.db.transaction("vaults").objectStore("vaults").get(this.scope),
    );
  }
  async initialize(
    passphrase: string,
    limits: {
      maxTransactions: number;
      maxAmountCents: string;
    },
  ): Promise<void> {
    if (
      !Number.isSafeInteger(limits.maxTransactions) ||
      limits.maxTransactions < 1 ||
      !/^[1-9][0-9]*$/.test(limits.maxAmountCents)
    )
      throw new Error("Invalid queue limits");
    if (await this.vault())
      throw new Error("Queue already exists; unlock instead");
    if (
      await request(
        this.db
          .transaction("records")
          .objectStore("records")
          .index("scope")
          .count(this.scope),
      )
    )
      throw new Error(
        "Queue metadata missing; preserve records and reconcile with server",
      );
    const salt = b64(crypto.getRandomValues(new Uint8Array(16)).buffer);
    const key = await this.derive(passphrase, salt);
    const state: State = {
      revision: 0,
      count: 0,
      amountCents: "0",
      chainHead: "0".repeat(64),
      ...limits,
    };
    const sealed = await this.seal(state, "vault", key);
    const tx = this.db.transaction("vaults", "readwrite", {
      durability: "strict",
    });
    const done = completed(tx);
    tx.objectStore("vaults").add({
      id: this.scope,
      salt,
      revision: 0,
      ...sealed,
    });
    await done;
    this.key = key;
  }
  async unlock(passphrase: string): Promise<void> {
    this.lock();
    const vault = await this.vault();
    if (!vault)
      throw new Error(
        "Queue storage missing; reconcile with server before re-provisioning",
      );
    const key = await this.derive(passphrase, vault.salt);
    const state = await this.unseal<State>(vault, "vault", key);
    if (state.revision !== vault.revision)
      throw new Error("Queue metadata conflict");
    this.key = key;
  }
  lock(): void {
    this.key = null;
  }
  isUnlocked(): boolean {
    return this.key !== null;
  }
  close(): void {
    this.lock();
    this.db.close();
  }
  async permit(): Promise<CashPermit | undefined> {
    const vault = await this.vault();
    if (!vault) throw new Error("Queue storage missing");
    return (await this.unseal<State>(vault, "vault")).permit;
  }
  async installPermit(permit: CashPermit): Promise<void> {
    const digest = await crypto.subtle.digest(
      "SHA-256",
      encode(
        JSON.stringify(
          [
            permit.instance_id,
            permit.branch_id,
            permit.staff_id,
            permit.terminal_id,
            permit.device_id,
          ].map((s) => s.toLowerCase()),
        ),
      ),
    );
    if (
      [...new Uint8Array(digest)]
        .map((b) => b.toString(16).padStart(2, "0"))
        .join("") !== this.scope
    )
      throw new Error("Permit scope mismatch");
    for (let attempt = 0; attempt < 5; attempt++) {
      const rows = await this.entries();
      const vault = await this.vault();
      if (!vault) throw new Error("Queue missing");
      const state = await this.unseal<State>(vault, "vault");
      if (state.permit?.id === permit.id) {
        if (
          JSON.stringify(ordered(state.permit)) !==
          JSON.stringify(ordered(permit))
        )
          throw new Error("Permit identity conflict");
        return;
      }
      if (rows.some((e) => e.data.permit_id && e.status !== "synced"))
        throw new Error("Reconcile prior permit first");
      const next = {
        ...state,
        revision: state.revision + 1,
        permit,
        used: {},
        slotCount: 0,
        cashCents: "0",
        maxTransactions: state.count + permit.policy.max_offline_transactions,
        maxAmountCents: (
          BigInt(state.amountCents) +
          decimal(permit.policy.max_offline_amount, 2)
        ).toString(),
      };
      const sealed = await this.seal(next, "vault");
      const tx = this.db.transaction("vaults", "readwrite", {
        durability: "strict",
      });
      const done = completed(tx);
      const latest = await request<Vault>(
        tx.objectStore("vaults").get(this.scope),
      );
      if (!latest || latest.revision !== vault.revision) {
        tx.abort();
        await done.catch(() => {});
        continue;
      }
      tx.objectStore("vaults").put({
        ...vault,
        revision: next.revision,
        ...sealed,
      });
      await done;
      return;
    }
    throw new Error("Queue changed; retry permit installation");
  }
  /** ACK changes are encrypted and use the same CAS as intake. Never erase paid evidence. */
  async transition(
    id: string,
    status: QueueStatus,
    acknowledgement?: QueueEntry["acknowledgement"],
  ): Promise<void> {
    if (!["pending", "syncing", "synced", "failed", "review"].includes(status))
      throw new Error("Invalid queue status");
    for (let attempt = 0; attempt < 5; attempt++) {
      await this.entries();
      const snapshot = this.db.transaction(["vaults", "records"]);
      const [vault, row] = await Promise.all([
        request<Vault>(snapshot.objectStore("vaults").get(this.scope)),
        request<Cipher | undefined>(snapshot.objectStore("records").get(id)),
      ]);
      if (!vault || !row || row.scope !== this.scope)
        throw new Error("Queue record unavailable");
      const state = await this.unseal<State>(vault, "vault");
      const entry = await this.unseal<QueueEntry>(row, id);
      if (entry.status === "synced" && status !== "review") return;
      if (
        acknowledgement &&
        (acknowledgement.client_transaction_id !== id ||
          !/^[0-9a-f-]{36}$/i.test(acknowledgement.inbox_id) ||
          (acknowledgement.financial_posted && !acknowledgement.sale_id))
      )
        throw new Error("Invalid server acknowledgement");
      if (
        status === "synced" &&
        (!acknowledgement ||
          !["synced", "completed"].includes(acknowledgement.status))
      )
        throw new Error("Final server acknowledgement required");
      const cipher = await this.seal(
        { ...entry, status, ...(acknowledgement ? { acknowledgement } : {}) },
        id,
      );
      const next = await this.seal(
        { ...state, revision: state.revision + 1 },
        "vault",
      );
      const tx = this.db.transaction(["vaults", "records"], "readwrite", {
        durability: "strict",
      });
      const done = completed(tx);
      const latest = await request<Vault>(
        tx.objectStore("vaults").get(this.scope),
      );
      if (!latest || latest.revision !== vault.revision) {
        tx.abort();
        await done.catch(() => {});
        continue;
      }
      tx.objectStore("records").put({ ...row, ...cipher });
      tx.objectStore("vaults").put({
        ...vault,
        revision: state.revision + 1,
        ...next,
      });
      await done;
      return;
    }
    throw new Error("Queue changed in another tab; retry sync");
  }
  async entries(): Promise<QueueEntry[]> {
    if (!this.key) throw new Error("Queue locked");
    const tx = this.db.transaction(["vaults", "records"]);
    const [vault, rows] = await Promise.all([
      request<Vault | undefined>(tx.objectStore("vaults").get(this.scope)),
      request<Cipher[]>(
        tx.objectStore("records").index("scope").getAll(this.scope),
      ),
    ]);
    if (!vault) throw new Error("Queue storage missing; reconcile with server");
    const state = await this.unseal<State>(vault, "vault");
    const entries = await Promise.all(
      rows.map((row) => this.unseal<QueueEntry>(row, row.id)),
    );
    if (
      state.revision !== vault.revision ||
      entries.length !== state.count ||
      entries.some(
        (e, i) =>
          e.id !== rows[i].id || !/^(0|[1-9][0-9]*)$/.test(e.amountCents),
      ) ||
      entries.reduce((sum, e) => sum + BigInt(e.amountCents), 0n).toString() !==
        state.amountCents
    )
      throw new Error(
        "Queue integrity conflict; preserve evidence and reconcile",
      );
    if (state.chainHead !== undefined) {
      let previous = "0".repeat(64),
        sequence = 0;
      for (const entry of [...entries].sort(
        (a, b) => (a.chain?.sequence || 0) - (b.chain?.sequence || 0),
      )) {
        if (
          !entry.chain ||
          entry.chain.sequence !== ++sequence ||
          entry.chain.previous !== previous ||
          entry.chain.hash !== (await chainHash(entry, previous, sequence))
        )
          throw new Error("Queue hash chain conflict; preserve evidence");
        previous = entry.chain.hash;
      }
      if (previous !== state.chainHead)
        throw new Error("Queue chain head conflict");
    }
    return entries;
  }
  async enqueue(
    input: Omit<QueueEntry, "id" | "status" | "clientCreatedAt">,
    authorizeCash?: () => boolean,
  ): Promise<QueueEntry> {
    if (!this.key) throw new Error("Queue locked");
    await this.entries();
    if (containsPatientData(input.data))
      throw new Error("Patient records cannot be queued offline");
    if (
      !/^(0|[1-9][0-9]*)$/.test(input.amountCents) ||
      !["sale.draft", "restaurant.order.draft", "delivery.request"].includes(
        input.kind,
      )
    )
      throw new Error("Invalid queue entry");
    let entry: QueueEntry = {
      ...input,
      id: crypto.randomUUID(),
      status: "pending",
      clientCreatedAt: new Date().toISOString(),
    };
    const bytes = encode(JSON.stringify(entry));
    if (bytes.byteLength > 65536) throw new Error("Queue entry exceeds limit");
    const storage = await EncryptedQueue.storage();
    if (storage.freeBytes < bytes.byteLength * 3 + 1048576)
      throw new Error("Insufficient browser storage headroom");
    for (let attempt = 0; attempt < 5; attempt++) {
      const vault = await this.vault();
      if (!vault)
        throw new Error("Queue storage missing; reconcile with server");
      const state = await this.unseal<State>(vault, "vault");
      if (state.revision !== vault.revision)
        throw new Error("Queue metadata conflict");
      if (state.chainHead === undefined && state.count > 0)
        throw new Error(
          "Legacy queue requires reconciliation before accepting new entries",
        );
      const cash = input.data.offline_cash === true;
      let cashUpdate: Partial<State> = {};
      if (cash) {
        if (!authorizeCash?.())
          throw new Error("Fresh cash authority required");
        const permit = state.permit;
        const clock = input.data.clock as
          | {
              eligible?: boolean;
              permitId?: string;
            }
          | undefined;
        if (
          !permit ||
          !clock?.eligible ||
          clock.permitId !== permit.id ||
          Date.parse(permit.expires_at) <= Date.now()
        )
          throw new Error("Fresh permit time required before collecting cash");
        const slot = permit.slots[state.slotCount || 0];
        if (!slot) throw new Error("Offline document slots exhausted");
        const used = { ...state.used };
        let total = 0n;
        const items = input.data.items as {
          product_id: string;
          quantity: string;
        }[];
        if (
          !Array.isArray(items) ||
          !items.length ||
          new Set(items.map((i) => i.product_id)).size !== items.length
        )
          throw new Error("Invalid offline cart");
        for (const item of items) {
          const product = permit.items.find(
            (p) => p.product_id === item.product_id,
          );
          const quantity = decimal(item.quantity, 4);
          if (!product || quantity <= 0n)
            throw new Error("Product not permitted offline");
          const consumed = BigInt(used[item.product_id] || "0") + quantity;
          if (consumed > decimal(product.quantity, 4))
            throw new Error("Offline SKU quota exceeded");
          used[item.product_id] = consumed.toString();
          const net =
            (quantity * decimal(product.unit_price, 2) + 5000n) / 10000n;
          const tax = (net * decimal(product.tax_rate, 2) + 5000n) / 10000n;
          total += net + tax;
        }
        const cashCents = BigInt(state.cashCents || "0") + total;
        if (
          total <= 0n ||
          cashCents > decimal(permit.policy.max_offline_amount, 2)
        )
          throw new Error("Offline cash budget exceeded");
        const money =
          (total / 100n).toString() +
          "." +
          (total % 100n).toString().padStart(2, "0");
        entry = {
          ...entry,
          amountCents: total.toString(),
          data: {
            ...input.data,
            permit_id: permit.id,
            slot_id: slot.id,
            total: money,
            provisional_receipt: "OFF-" + permit.id + "-" + slot.ordinal,
          },
        };
        cashUpdate = {
          used,
          slotCount: (state.slotCount || 0) + 1,
          cashCents: cashCents.toString(),
        };
      }
      const amount = BigInt(state.amountCents) + BigInt(entry.amountCents);
      if (
        state.count >= state.maxTransactions ||
        amount > BigInt(state.maxAmountCents)
      )
        throw new Error(
          "Offline queue limit reached; existing records retained",
        );
      const previous = state.chainHead || "0".repeat(64);
      entry = {
        ...entry,
        chain: {
          sequence: state.count + 1,
          previous,
          hash: await chainHash(entry, previous, state.count + 1),
        },
      };
      const next = {
        ...state,
        ...cashUpdate,
        chainHead: entry.chain!.hash,
        revision: state.revision + 1,
        count: state.count + 1,
        amountCents: amount.toString(),
      };
      const nextCipher = await this.seal(next, "vault");
      const sealed = await this.seal(entry, entry.id);
      if (encode(JSON.stringify(entry)).byteLength > 65536)
        throw new Error("Queue entry exceeds limit");
      if (cash && !authorizeCash?.())
        throw new Error(
          "Cash authority changed; retry without collecting money",
        );
      const tx = this.db.transaction(["vaults", "records"], "readwrite", {
        durability: "strict",
      });
      const done = completed(tx);
      const latest = await request<Vault>(
        tx.objectStore("vaults").get(this.scope),
      );
      if (!latest || latest.revision !== vault.revision) {
        tx.abort();
        await done.catch(() => {});
        continue;
      }
      tx.objectStore("records").add({
        id: entry.id,
        scope: this.scope,
        ...sealed,
      });
      tx.objectStore("vaults").put({
        ...vault,
        revision: next.revision,
        ...nextCipher,
      });
      await done;
      return entry;
    }
    throw new Error(
      "Queue changed in another tab; retry without collecting money",
    );
  }
}
function decimal(value: string, scale: number): bigint {
  if (
    typeof value !== "string" ||
    !new RegExp("^\\d{1,10}(\\.\\d{1," + scale + "})?$").test(value)
  )
    throw new Error("Invalid decimal");
  const [whole, fraction = ""] = value.split(".");
  return (
    BigInt(whole) * 10n ** BigInt(scale) + BigInt(fraction.padEnd(scale, "0"))
  );
}
function containsPatientData(value: unknown, depth = 0): boolean {
  if (depth > 32) return true;
  if (!value || typeof value !== "object") return false;
  return Object.entries(value).some(([key, item]) => {
    const name = key.replace(/[_-]/g, "").toLowerCase();
    return (
      name.startsWith("patient") ||
      ["medicalrecord", "clinicalnote", "allergies", "birthdate"].includes(
        name,
      ) ||
      containsPatientData(item, depth + 1)
    );
  });
}
function ordered(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(ordered);
  if (value && typeof value === "object")
    return Object.fromEntries(
      Object.entries(value)
        .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0))
        .map(([key, item]) => [key, ordered(item)]),
    );
  return value;
}
async function chainHash(
  entry: QueueEntry,
  previous: string,
  sequence: number,
): Promise<string> {
  const bytes = encode(
    JSON.stringify([
      previous,
      sequence,
      entry.id,
      entry.kind,
      entry.clientCreatedAt,
      entry.amountCents,
      entry.data,
    ]),
  );
  return [...new Uint8Array(await crypto.subtle.digest("SHA-256", bytes))]
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}
