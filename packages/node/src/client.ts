import { HttpClient, type FetchLike } from "./http.js";
import { Messages } from "./resources/messages.js";
import { Senders } from "./resources/senders.js";

/** Replace with the production URL before the first release (see docs/RELEASING.md). */
export const DEFAULT_BASE_URL = "https://api.notify.example/v1";

export interface NotifyOptions {
  /** An API key from the portal. Defaults to the `NOTIFY_API_KEY` environment variable. */
  apiKey?: string;
  /** Defaults to the `NOTIFY_BASE_URL` environment variable, then the production API. */
  baseUrl?: string;
  /** Per-request timeout. Defaults to 30 seconds. */
  timeoutMs?: number;
  /** Retries for network errors, 429 and 5xx. Defaults to 2. */
  maxRetries?: number;
  /** A custom `fetch` (proxies, instrumentation, tests). Defaults to the global one. */
  fetch?: FetchLike;
  /** Identifies your app in the User-Agent, which helps support. */
  appInfo?: { name: string; version?: string };
}

/**
 * ```ts
 * const notify = new Notify({ apiKey: process.env.NOTIFY_API_KEY });
 * const { id } = await notify.messages.send({
 *   to: "01712345678",
 *   body: "Your code is 123456",
 *   messageType: "otp",
 *   senderId: "MyShop",
 * });
 * ```
 */
export class Notify {
  readonly messages: Messages;
  readonly senders: Senders;

  constructor(options: NotifyOptions = {}) {
    const apiKey = options.apiKey ?? process.env["NOTIFY_API_KEY"];
    if (!apiKey) {
      throw new TypeError(
        "No API key. Pass { apiKey } or set the NOTIFY_API_KEY environment variable.",
      );
    }
    const http = new HttpClient({
      apiKey,
      baseUrl: options.baseUrl ?? process.env["NOTIFY_BASE_URL"] ?? DEFAULT_BASE_URL,
      timeoutMs: options.timeoutMs ?? 30_000,
      maxRetries: options.maxRetries ?? 2,
      fetch: options.fetch ?? ((input, init) => fetch(input, init)),
      appInfo: options.appInfo,
    });
    this.messages = new Messages(http);
    this.senders = new Senders(http);
  }
}
