import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

import { SignatureVerificationError, constructEvent } from "../src/index.js";

interface Case {
  name: string;
  header: string;
  now: number;
  rawBody?: string;
  valid: boolean;
  reason?: string;
}

const fixture = JSON.parse(
  readFileSync(new URL("../../../spec/fixtures/webhook-signature.json", import.meta.url), "utf8"),
) as { secret: string; rawBody: string; toleranceSeconds: number; cases: Case[] };

describe("constructEvent (shared vectors)", () => {
  it.each(fixture.cases)("$name", (c) => {
    const run = () =>
      constructEvent(c.rawBody ?? fixture.rawBody, c.header, fixture.secret, {
        now: c.now,
        toleranceSeconds: fixture.toleranceSeconds,
      });
    if (c.valid) {
      expect(run().eventName).toBe("message.delivered");
    } else {
      try {
        run();
        expect.unreachable("expected a SignatureVerificationError");
      } catch (error) {
        expect(error).toBeInstanceOf(SignatureVerificationError);
        expect((error as SignatureVerificationError).reason).toBe(c.reason);
      }
    }
  });

  it("accepts a Buffer body", () => {
    const valid = fixture.cases[0]!;
    const event = constructEvent(Buffer.from(fixture.rawBody), valid.header, fixture.secret, {
      now: valid.now,
    });
    expect(event.payload.status).toBe("delivered");
  });

  it("rejects a missing header", () => {
    expect(() => constructEvent(fixture.rawBody, undefined, fixture.secret)).toThrow(
      SignatureVerificationError,
    );
  });
});
