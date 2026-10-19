import { test } from "node:test";
import assert from "node:assert/strict";
import { createHmac } from "node:crypto";
import {
  verifySignedPayload,
  verificationResponse,
} from "./webhook-verifier.mjs";
const secret = "a".repeat(64);
const now = 1792386000;
const sign = (body, timestamp = now) =>
  `t=${timestamp},v1=${createHmac("sha256", secret).update(`${timestamp}.`).update(body).digest("hex")}`;

test("checks original bytes and rejects a modified or reserialized body", () => {
  const body = Buffer.from('{ "id": "fixture", "schema_version": 1 }');
  assert.equal(
    verifySignedPayload(body, sign(body), secret, now).id,
    "fixture",
  );
  assert.throws(() =>
    verifySignedPayload(
      Buffer.from('{"schema_version":1,"id":"fixture"}'),
      sign(body),
      secret,
      now,
    ),
  );
});
test("rejects stale/future signatures and malformed digests", () => {
  const body = Buffer.from("{}");
  for (const signature of [
    sign(body, now - 301),
    sign(body, now + 301),
    "t=1,v1=abc",
    sign(body).toUpperCase(),
  ]) {
    assert.throws(() => verifySignedPayload(body, signature, secret, now));
  }
});
test("challenge response requires a signed payload first and uses a separate HMAC context", () => {
  const body = Buffer.from(
    JSON.stringify({
      type: "endpoint.verification",
      schema_version: 1,
      challenge: "b".repeat(64),
    }),
  );
  const payload = verifySignedPayload(body, sign(body), secret, now);
  const response = verificationResponse(payload, secret);
  assert.equal(
    response.signature,
    createHmac("sha256", secret)
      .update("verify:" + payload.challenge)
      .digest("hex"),
  );
  assert.notEqual(response.signature, sign(body).split("v1=")[1]);
});
test("rejects oversized body and never returns secret through errors", () => {
  const body = Buffer.alloc(65537);
  assert.throws(() => verifySignedPayload(body, sign(body), secret, now), {
    message: "Invalid webhook",
  });
});
