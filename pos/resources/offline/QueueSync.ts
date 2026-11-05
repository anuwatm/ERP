import { EncryptedQueue, QueueEntry } from "./EncryptedQueue";
type Ack = NonNullable<QueueEntry["acknowledgement"]>;
/** Transport must authenticate afresh. A lost response replays the original immutable identity. */
export class QueueSync {
  private running = false;
  constructor(
    private queue: EncryptedQueue,
    private deviceId: string,
    private authenticate: () => Promise<void>,
    private send: (payload: Record<string, unknown>) => Promise<Ack>,
  ) {}
  async run(): Promise<void> {
    if (this.running) return;
    this.running = true;
    try {
      await this.authenticate();
      for (const entry of await this.queue.entries()) {
        if (entry.status === "synced") continue;
        await this.queue.transition(entry.id, "syncing");
        try {
          const ack = await this.send({
            device_id: this.deviceId,
            client_transaction_id: entry.id,
            kind: entry.kind,
            client_created_at: entry.clientCreatedAt,
            data: entry.data,
          });
          if (!["review", "synced", "completed"].includes(ack.status))
            throw new Error("Unrecognized server acknowledgement");
          await this.queue.transition(
            entry.id,
            ack.status === "review" ? "review" : "synced",
            ack,
          );
        } catch (error) {
          await this.queue.transition(entry.id, "failed");
          throw error;
        }
      }
    } finally {
      this.running = false;
    }
  }
}
