import type { HttpClient } from "../http.js";
import type { RequestOptions, SenderId } from "../types.js";

export class Senders {
  constructor(private readonly http: HttpClient) {}

  /** Requests a sender ID. It stays `pending` until an operator approves it. */
  create(value: string, options?: RequestOptions): Promise<SenderId> {
    return this.http.request<SenderId>({ method: "POST", path: "/senders", body: { value }, options });
  }

  list(options?: RequestOptions): Promise<SenderId[]> {
    return this.http.request<SenderId[]>({ method: "GET", path: "/senders", options });
  }
}
