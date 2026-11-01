export type ClockPermit = {id: string; version: number; serverNow: string; expiresAt: string};
export type ClockEvidence = {
  eligible: boolean; reason: string | null; contextId: string | null;
  permitId: string | null; permitVersion: number | null;
  clientCreatedAt: string; elapsedMs: number | null;
};

/** Honest-client guard only. Server must independently validate permit/quota/time bounds. */
export class TrustedClock {
  private sample: {permit: ClockPermit; contextId: string; mono: number; wall: number; origin: number; last: number; server: number; expiry: number; rtt: number} | null = null;
  private reason: string | null = 'online_time_required';
  private timer: ReturnType<typeof setInterval>;
  private hidden = () => { if (document.visibilityState === 'hidden') this.invalidate('context_suspended'); };
  private leaving = () => this.invalidate('context_restarted');

  constructor() {
    this.timer = setInterval(() => this.evidence(), 1000);
    document.addEventListener('visibilitychange', this.hidden);
    window.addEventListener('pagehide', this.leaving);
  }

  // Call only with a fresh authenticated live response, never a cached/stored sample.
  begin(permit: ClockPermit, requestStartedAt: number): void {
    this.invalidate('invalid_time_sample');
    const mono = performance.now(), wall = Date.now();
    const server = Date.parse(permit.serverNow), expiry = Date.parse(permit.expiresAt);
    const rtt = mono - requestStartedAt;
    if (!Number.isSafeInteger(permit.version) || permit.version < 1 || !Number.isFinite(server) || !Number.isFinite(expiry) || expiry <= server || rtt < 0 || rtt > 5000) throw new Error('Fresh online time required');
    this.sample = {permit:{...permit}, contextId:crypto.randomUUID(), mono, wall, origin:performance.timeOrigin, last:mono, server, expiry, rtt};
    this.reason = null;
  }

  invalidate(reason: string): void { this.reason = reason; }

  evidence(): ClockEvidence {
    const s = this.sample, mono = performance.now(), wall = Date.now();
    if (s && !this.reason) {
      const elapsed = mono - s.mono;
      if (performance.timeOrigin !== s.origin || elapsed < 0 || mono - s.last > 5000 || mono < s.last) this.invalidate('context_suspended');
      else if (Math.abs(wall - s.wall - elapsed) > 2000) this.invalidate('clock_changed');
      else if (s.server + elapsed + s.rtt >= s.expiry) this.invalidate('permit_expired');
      s.last = mono;
    }
    return {eligible:!!s && !this.reason, reason:this.reason, contextId:s?.contextId || null, permitId:s?.permit.id || null, permitVersion:s?.permit.version ?? null, clientCreatedAt:new Date(wall).toISOString(), elapsedMs:s ? Math.max(0, Math.floor(mono-s.mono)) : null};
  }

  close(): void {
    this.invalidate('context_closed');
    clearInterval(this.timer);
    document.removeEventListener('visibilitychange', this.hidden);
    window.removeEventListener('pagehide', this.leaving);
  }
}
