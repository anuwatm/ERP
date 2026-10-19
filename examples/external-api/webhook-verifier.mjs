import { createHmac, timingSafeEqual } from "node:crypto";

// Call with the original raw bytes BEFORE JSON parsing. Enforce the same size cap in the HTTP server.
export function verifySignedPayload(
  rawBody,
  signature,
  secret,
  nowSeconds = Math.floor(Date.now() / 1000),
) {
  if (
    !Buffer.isBuffer(rawBody) ||
    rawBody.length > 65536 ||
    typeof secret !== "string" ||
    !/^[a-f0-9]{64}$/.test(secret)
  )
    throw new Error("Invalid webhook");
  const match = /^t=([0-9]{1,12}),v1=([a-f0-9]{64})$/.exec(signature ?? "");
  if (!match || Math.abs(nowSeconds - Number(match[1])) > 300)
    throw new Error("Invalid webhook");
  const expected = createHmac("sha256", secret)
    .update(match[1] + ".")
    .update(rawBody)
    .digest();
  if (!timingSafeEqual(expected, Buffer.from(match[2], "hex")))
    throw new Error("Invalid webhook");
  try {
    return JSON.parse(rawBody.toString("utf8"));
  } catch {
    throw new Error("Invalid webhook");
  }
}

export function verificationResponse(payload, secret) {
  if (
    payload?.type !== "endpoint.verification" ||
    payload.schema_version !== 1 ||
    !/^[a-f0-9]{64}$/.test(payload.challenge ?? "")
  )
    throw new Error("Invalid challenge");
  return {
    challenge: payload.challenge,
    signature: createHmac("sha256", secret)
      .update("verify:" + payload.challenge)
      .digest("hex"),
  };
}
