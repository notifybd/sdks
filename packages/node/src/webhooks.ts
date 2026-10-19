import { createHmac, timingSafeEqual } from "node:crypto";

import { SignatureVerificationError } from "./errors.js";
import type { WebhookEvent } from "./types.js";

export const SIGNATURE_HEADER = "x-notify-signature";
export const DEFAULT_TOLERANCE_SECONDS = 300;

export interface VerifyOptions {
  /** Reject events whose timestamp is further than this from now. Defaults to 300 seconds. */
  toleranceSeconds?: number;
  /** Current time in unix seconds. For tests. */
  now?: number;
}

interface ParsedHeader {
  timestamp: number;
  signatures: string[];
}

function parseHeader(header: string): ParsedHeader | null {
  let timestamp: number | undefined;
  const signatures: string[] = [];
  for (const part of header.split(",")) {
    const separator = part.indexOf("=");
    if (separator < 0) continue;
    const key = part.slice(0, separator).trim();
    const value = part.slice(separator + 1).trim();
    if (key === "t" && /^\d+$/.test(value)) timestamp = Number(value);
    else if (key === "v1" && value) signatures.push(value);
  }
  return timestamp !== undefined && signatures.length > 0 ? { timestamp, signatures } : null;
}

/**
 * Checks that a webhook really came from Notify and returns its parsed body.
 *
 * Pass the **raw** request body exactly as received: parsing and re-serialising it changes the
 * bytes and breaks the signature. `header` is the value of `X-Notify-Signature`.
 *
 * @throws SignatureVerificationError when the header is malformed, the timestamp is outside the
 *   tolerance window (replay protection) or no signature matches.
 */
export function constructEvent(
  rawBody: string | Uint8Array,
  header: string | null | undefined,
  secret: string,
  options: VerifyOptions = {},
): WebhookEvent {
  verifySignature(rawBody, header, secret, options);
  const text = typeof rawBody === "string" ? rawBody : Buffer.from(rawBody).toString("utf8");
  try {
    return JSON.parse(text) as WebhookEvent;
  } catch {
    throw new SignatureVerificationError("invalid_payload", "The webhook body is not valid JSON");
  }
}

/** Like `constructEvent` but only verifies; returns nothing and throws on failure. */
export function verifySignature(
  rawBody: string | Uint8Array,
  header: string | null | undefined,
  secret: string,
  options: VerifyOptions = {},
): void {
  const parsed = header ? parseHeader(header) : null;
  if (!parsed) {
    throw new SignatureVerificationError(
      "malformed_header",
      "The X-Notify-Signature header is missing or malformed",
    );
  }

  const tolerance = options.toleranceSeconds ?? DEFAULT_TOLERANCE_SECONDS;
  const now = options.now ?? Math.floor(Date.now() / 1000);
  if (Math.abs(now - parsed.timestamp) > tolerance) {
    throw new SignatureVerificationError(
      "timestamp_outside_tolerance",
      "The webhook timestamp is outside the allowed window",
    );
  }

  const body = typeof rawBody === "string" ? Buffer.from(rawBody, "utf8") : Buffer.from(rawBody);
  const expected = createHmac("sha256", secret)
    .update(`${parsed.timestamp}.`)
    .update(body)
    .digest();

  // More than one v1 appears while a secret is being rotated; any match is enough.
  const matches = parsed.signatures.some((signature) => {
    const presented = Buffer.from(signature, "hex");
    return presented.length === expected.length && timingSafeEqual(presented, expected);
  });
  if (!matches) {
    throw new SignatureVerificationError("signature_mismatch", "No signature matches the payload");
  }
}
