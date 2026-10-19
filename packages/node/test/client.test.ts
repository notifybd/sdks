import { describe, expect, it, vi } from "vitest";

import {
  ApiConnectionError,
  AuthenticationError,
  ConflictError,
  Notify,
  RateLimitError,
  UnprocessableError,
  ValidationError,
} from "../src/index.js";

const accepted = {
  id: "018f0000-0000-7000-8000-000000000001",
  status: "accepted",
  reference: null,
  createdAt: "2026-01-01T00:00:00.000Z",
};

const json = (body: unknown, status = 200, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { "content-type": "application/json", ...headers } });

const params = { to: "01712345678", body: "Your code is 1", messageType: "otp", senderId: "Shop" } as const;

function client(fetchImpl: ReturnType<typeof vi.fn>, extra = {}) {
  return new Notify({ apiKey: "nk_test", baseUrl: "https://api.test/v1", fetch: fetchImpl, maxRetries: 2, ...extra });
}

describe("Notify", () => {
  it("requires an API key", () => {
    const saved = process.env["NOTIFY_API_KEY"];
    delete process.env["NOTIFY_API_KEY"];
    expect(() => new Notify()).toThrow(/No API key/);
    if (saved) process.env["NOTIFY_API_KEY"] = saved;
  });

  it("sends with auth, a user agent and an idempotency key", async () => {
    const fetchImpl = vi.fn().mockResolvedValue(json(accepted, 202));
    const result = await client(fetchImpl).messages.send(params);

    expect(result.id).toBe(accepted.id);
    const [url, init] = fetchImpl.mock.calls[0]!;
    expect(url).toBe("https://api.test/v1/messages");
    expect(init.method).toBe("POST");
    expect(init.headers["Authorization"]).toBe("Bearer nk_test");
    expect(init.headers["User-Agent"]).toMatch(/^notify-node\//);
    expect(init.headers["Idempotency-Key"]).toMatch(/^[0-9a-f-]{36}$/);
    expect(JSON.parse(init.body)).toEqual(params);
  });

  it("reuses one idempotency key across its own retries", async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(json({ error: { code: "UPSTREAM_UNAVAILABLE", message: "x" } }, 503, { "retry-after": "0" }))
      .mockResolvedValueOnce(json(accepted, 202));
    await client(fetchImpl).messages.send(params);

    expect(fetchImpl).toHaveBeenCalledTimes(2);
    const keys = fetchImpl.mock.calls.map((call) => call[1].headers["Idempotency-Key"]);
    expect(keys[0]).toBe(keys[1]);
  });

  it("honours a caller supplied idempotency key", async () => {
    const fetchImpl = vi.fn().mockResolvedValue(json(accepted, 202));
    await client(fetchImpl).messages.send(params, { idempotencyKey: "order-42" });
    expect(fetchImpl.mock.calls[0]![1].headers["Idempotency-Key"]).toBe("order-42");
  });

  it("retries network errors and then reports them", async () => {
    const fetchImpl = vi.fn().mockRejectedValue(new Error("ECONNRESET"));
    await expect(client(fetchImpl, { maxRetries: 1 }).messages.send(params)).rejects.toBeInstanceOf(
      ApiConnectionError,
    );
    expect(fetchImpl).toHaveBeenCalledTimes(2);
  });

  it.each([
    [400, "VALIDATION_ERROR", ValidationError],
    [401, "UNAUTHENTICATED", AuthenticationError],
    [409, "CONFLICT", ConflictError],
    [422, "POLICY_REJECTION", UnprocessableError],
  ])("maps %i to a typed error and does not retry", async (status, code, type) => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValue(json({ error: { code, message: "nope", requestId: "req-1" } }, status));
    const error = await client(fetchImpl).messages.send(params).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(type);
    expect(error).toMatchObject({ status, code, requestId: "req-1" });
    expect(fetchImpl).toHaveBeenCalledTimes(1);
  });

  it("surfaces Retry-After on a 429 once retries are spent", async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValue(json({ error: { code: "RATE_LIMITED", message: "slow" } }, 429, { "retry-after": "0.001" }));
    const error = await client(fetchImpl, { maxRetries: 0 }).messages.send(params).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(RateLimitError);
    expect((error as RateLimitError).retryAfterSeconds).toBeGreaterThan(0);
  });

  it("retries a GET on a 5xx", async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(json({ error: { code: "INTERNAL_ERROR", message: "x" } }, 500, { "retry-after": "0" }))
      .mockResolvedValueOnce(json({ id: "m1" }));
    expect(await client(fetchImpl).messages.get("m1")).toEqual({ id: "m1" });
  });

  it("pages through listAll using the cursor", async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(json({ data: [{ id: "a" }, { id: "b" }], nextCursor: "c1" }))
      .mockResolvedValueOnce(json({ data: [{ id: "c" }], nextCursor: null }));
    const ids: string[] = [];
    for await (const message of client(fetchImpl).messages.listAll({ limit: 2 })) ids.push(message.id);

    expect(ids).toEqual(["a", "b", "c"]);
    expect(fetchImpl.mock.calls[0]![0]).toBe("https://api.test/v1/messages?limit=2");
    expect(fetchImpl.mock.calls[1]![0]).toBe("https://api.test/v1/messages?limit=2&cursor=c1");
  });

  it("exposes hasMore on a page", async () => {
    const fetchImpl = vi.fn().mockResolvedValue(json({ data: [], nextCursor: "next" }));
    const page = await client(fetchImpl).messages.list();
    expect(page.hasMore).toBe(true);
  });

  it("creates and lists senders", async () => {
    const sender = { id: "s1", value: "Shop", status: "pending", approvedAt: null, createdAt: "x" };
    const fetchImpl = vi.fn().mockResolvedValueOnce(json(sender, 201)).mockResolvedValueOnce(json([sender]));
    const notify = client(fetchImpl);
    expect((await notify.senders.create("Shop")).status).toBe("pending");
    expect(await notify.senders.list()).toHaveLength(1);
  });
});
