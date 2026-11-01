export type QueueScope = {
  instanceId: string; branchId: string; staffId: string; terminalId: string; deviceId: string;
};
export type QueueStatus = 'pending' | 'syncing' | 'synced' | 'failed' | 'review';
export type QueueEntry = {
  id: string; kind: 'sale.draft' | 'restaurant.order.draft' | 'delivery.request';
  status: QueueStatus; clientCreatedAt: string; amountCents: string; data: Record<string, unknown>;
};
type State = { revision: number; count: number; amountCents: string; maxTransactions: number; maxAmountCents: string };
type Vault = { id: string; salt: string; revision: number; iv: string; ciphertext: string };
type Cipher = { id: string; scope: string; iv: string; ciphertext: string };
const encode = (value: string) => new TextEncoder().encode(value).buffer as ArrayBuffer;
const b64 = (bytes: ArrayBuffer) => {
  const data = new Uint8Array(bytes);
  let text = '';
  for (let i = 0; i < data.length; i += 8192) text += String.fromCharCode(...data.subarray(i, i + 8192));
  return btoa(text);
};
const unb64 = (value: string) => Uint8Array.from(atob(value), c => c.charCodeAt(0)).buffer as ArrayBuffer;
const request = <T>(value: IDBRequest<T>) => new Promise<T>((resolve, reject) => {
  value.onsuccess = () => resolve(value.result); value.onerror = () => reject(value.error);
});
const completed = (tx: IDBTransaction) => new Promise<void>((resolve, reject) => {
  tx.oncomplete = () => resolve(); tx.onabort = () => reject(tx.error || new Error('Queue transaction aborted'));
  tx.onerror = () => reject(tx.error || new Error('Queue write failed'));
});

/** Storage primitive only: queue durability never grants quota, sale or tax authority. */
export class EncryptedQueue {
  private key: CryptoKey | null = null;
  private constructor(private db: IDBDatabase, private scope: string) {
    window.addEventListener('pagehide', () => this.lock());
  }

  static async open(scope: QueueScope): Promise<EncryptedQueue> {
    if (!window.isSecureContext || !crypto.subtle || !indexedDB) throw new Error('Secure browser storage unavailable');
    const ordered = [scope.instanceId, scope.branchId, scope.staffId, scope.terminalId, scope.deviceId];
    if (ordered.some(s => !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(s))) throw new Error('Invalid queue scope');
    const digest = await crypto.subtle.digest('SHA-256', encode(JSON.stringify(ordered.map(s => s.toLowerCase()))));
    const id = [...new Uint8Array(digest)].map(b => b.toString(16).padStart(2, '0')).join('');
    const opening = indexedDB.open('company-pos-offline-v1', 1);
    opening.onupgradeneeded = () => {
      opening.result.createObjectStore('vaults', {keyPath: 'id'});
      const records = opening.result.createObjectStore('records', {keyPath: 'id'});
      records.createIndex('scope', 'scope');
    };
    const db = await request(opening);
    db.onversionchange = () => db.close();
    return new EncryptedQueue(db, id);
  }

  static async storage(): Promise<{persistent: boolean; freeBytes: number}> {
    if (!navigator.storage?.estimate) throw new Error('Cannot verify browser storage headroom');
    const persistent = navigator.storage.persist ? await navigator.storage.persist().catch(() => false) : false;
    const {quota, usage} = await navigator.storage.estimate();
    if (quota === undefined || usage === undefined) throw new Error('Browser storage estimate unavailable');
    return {persistent, freeBytes: quota - usage};
  }

  private async derive(passphrase: string, salt: string): Promise<CryptoKey> {
    if (Array.from(passphrase).length < 12 || passphrase.length > 128 || !passphrase.trim()) throw new Error('Unlock passphrase requires 12–128 characters');
    const material = await crypto.subtle.importKey('raw', encode(passphrase), 'PBKDF2', false, ['deriveKey']);
    return crypto.subtle.deriveKey({name:'PBKDF2', hash:'SHA-256', salt:unb64(salt), iterations:600000}, material, {name:'AES-GCM', length:256}, false, ['encrypt', 'decrypt']);
  }

  private async seal(value: unknown, id: string, key = this.key): Promise<{iv: string; ciphertext: string}> {
    if (!key) throw new Error('Queue locked');
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ciphertext = await crypto.subtle.encrypt({name:'AES-GCM', iv, additionalData:encode(this.scope+'|'+id), tagLength:128}, key, encode(JSON.stringify(value)));
    return {iv:b64(iv.buffer), ciphertext:b64(ciphertext)};
  }

  private async unseal<T>(cipher: {iv: string; ciphertext: string}, id: string, key = this.key): Promise<T> {
    if (!key) throw new Error('Queue locked');
    const bytes = await crypto.subtle.decrypt({name:'AES-GCM', iv:unb64(cipher.iv), additionalData:encode(this.scope+'|'+id), tagLength:128}, key, unb64(cipher.ciphertext));
    return JSON.parse(new TextDecoder().decode(bytes)) as T;
  }

  private async vault(): Promise<Vault | undefined> {
    return request(this.db.transaction('vaults').objectStore('vaults').get(this.scope));
  }

  async initialize(passphrase: string, limits: {maxTransactions: number; maxAmountCents: string}): Promise<void> {
    if (!Number.isSafeInteger(limits.maxTransactions) || limits.maxTransactions < 1 || !/^[1-9][0-9]*$/.test(limits.maxAmountCents)) throw new Error('Invalid queue limits');
    if (await this.vault()) throw new Error('Queue already exists; unlock instead');
    const salt = b64(crypto.getRandomValues(new Uint8Array(16)).buffer);
    const key = await this.derive(passphrase, salt);
    const state: State = {revision:0, count:0, amountCents:'0', ...limits};
    const sealed = await this.seal(state, 'vault', key);
    const tx = this.db.transaction('vaults', 'readwrite', {durability:'strict'});
    const done = completed(tx);
    tx.objectStore('vaults').add({id:this.scope, salt, revision:0, ...sealed});
    await done;
    this.key = key;
  }

  async unlock(passphrase: string): Promise<void> {
    this.lock();
    const vault = await this.vault();
    if (!vault) throw new Error('Queue storage missing; reconcile with server before re-provisioning');
    const key = await this.derive(passphrase, vault.salt);
    const state = await this.unseal<State>(vault, 'vault', key);
    if (state.revision !== vault.revision) throw new Error('Queue metadata conflict');
    this.key = key;
  }

  lock(): void { this.key = null; }
  close(): void { this.lock(); this.db.close(); }

  async entries(): Promise<QueueEntry[]> {
    if (!this.key) throw new Error('Queue locked');
    const tx = this.db.transaction(['vaults', 'records']);
    const [vault, rows] = await Promise.all([
      request<Vault | undefined>(tx.objectStore('vaults').get(this.scope)),
      request<Cipher[]>(tx.objectStore('records').index('scope').getAll(this.scope)),
    ]);
    if (!vault) throw new Error('Queue storage missing; reconcile with server');
    const state = await this.unseal<State>(vault, 'vault');
    const entries = await Promise.all(rows.map(row => this.unseal<QueueEntry>(row, row.id)));
    if (state.revision !== vault.revision || entries.length !== state.count ||
      entries.some((e, i) => e.id !== rows[i].id || !/^(0|[1-9][0-9]*)$/.test(e.amountCents)) ||
      entries.reduce((sum, e) => sum + BigInt(e.amountCents), 0n).toString() !== state.amountCents) throw new Error('Queue integrity conflict; preserve evidence and reconcile');
    return entries;
  }

  async enqueue(input: Omit<QueueEntry, 'id' | 'status' | 'clientCreatedAt'>): Promise<QueueEntry> {
    if (!this.key) throw new Error('Queue locked');
    await this.entries();
    if (!/^(0|[1-9][0-9]*)$/.test(input.amountCents) || !['sale.draft','restaurant.order.draft','delivery.request'].includes(input.kind)) throw new Error('Invalid queue entry');
    const entry: QueueEntry = {...input, id:crypto.randomUUID(), status:'pending', clientCreatedAt:new Date().toISOString()};
    const bytes = encode(JSON.stringify(entry));
    if (bytes.byteLength > 65536) throw new Error('Queue entry exceeds limit');
    const storage = await EncryptedQueue.storage();
    if (storage.freeBytes < bytes.byteLength * 3 + 1048576) throw new Error('Insufficient browser storage headroom');
    const sealed = await this.seal(entry, entry.id);
    for (let attempt = 0; attempt < 5; attempt++) {
      const vault = await this.vault();
      if (!vault) throw new Error('Queue storage missing; reconcile with server');
      const state = await this.unseal<State>(vault, 'vault');
      if (state.revision !== vault.revision) throw new Error('Queue metadata conflict');
      const amount = BigInt(state.amountCents) + BigInt(entry.amountCents);
      if (state.count >= state.maxTransactions || amount > BigInt(state.maxAmountCents)) throw new Error('Offline queue limit reached; existing records retained');
      const next = {...state, revision:state.revision+1, count:state.count+1, amountCents:amount.toString()};
      const nextCipher = await this.seal(next, 'vault');
      const tx = this.db.transaction(['vaults','records'], 'readwrite', {durability:'strict'});
      const done = completed(tx);
      const latest = await request<Vault>(tx.objectStore('vaults').get(this.scope));
      if (!latest || latest.revision !== vault.revision) {
        tx.abort(); await done.catch(() => {}); continue;
      }
      tx.objectStore('records').add({id:entry.id, scope:this.scope, ...sealed});
      tx.objectStore('vaults').put({...vault, revision:next.revision, ...nextCipher});
      await done;
      return entry;
    }
    throw new Error('Queue changed in another tab; retry without collecting money');
  }
}
