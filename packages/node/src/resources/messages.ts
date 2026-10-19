import { randomUUID } from "node:crypto";

import type { HttpClient } from "../http.js";
import type {
  DeliveryEvent,
  ListParams,
  Message,
  MessageEvent,
  Page,
  RequestOptions,
  SendMessageOptions,
  SendMessageParams,
  SendMessageResponse,
} from "../types.js";

interface WirePage<T> {
  data: T[];
  nextCursor: string | null;
}

const toPage = <T>(wire: WirePage<T>): Page<T> => ({
  data: wire.data,
  nextCursor: wire.nextCursor,
  hasMore: wire.nextCursor !== null,
});

export class Messages {
  constructor(private readonly http: HttpClient) {}

  /**
   * Submits a message. It is delivered to the recipient's app first and falls back to SMS if the
   * app cannot be reached. The call returns once the message is accepted (HTTP 202).
   *
   * Every call carries an idempotency key (yours, or a generated one) so the SDK can retry network
   * errors and 5xx responses without ever sending the message twice.
   */
  send(params: SendMessageParams, options: SendMessageOptions = {}): Promise<SendMessageResponse> {
    const { idempotencyKey, ...requestOptions } = options;
    return this.http.request<SendMessageResponse>({
      method: "POST",
      path: "/messages",
      body: params,
      headers: { "Idempotency-Key": idempotencyKey ?? randomUUID() },
      retryable: true,
      options: requestOptions,
    });
  }

  get(id: string, options?: RequestOptions): Promise<Message> {
    return this.http.request<Message>({
      method: "GET",
      path: `/messages/${encodeURIComponent(id)}`,
      options,
    });
  }

  /** One page of messages, newest first. */
  async list(params: ListParams = {}, options?: RequestOptions): Promise<Page<Message>> {
    return toPage(await this.page<Message>("/messages", params, options));
  }

  /** Every message, newest first, fetching pages as you iterate: `for await (const m of ...)`. */
  async *listAll(params: Omit<ListParams, "cursor"> = {}, options?: RequestOptions): AsyncGenerator<Message> {
    yield* this.iterate<Message>("/messages", params, options);
  }

  /** The message's lifecycle events, newest first. */
  async events(id: string, params: ListParams = {}, options?: RequestOptions): Promise<Page<MessageEvent>> {
    return toPage(await this.page<MessageEvent>(`/messages/${encodeURIComponent(id)}/events`, params, options));
  }

  /** The message's delivery attempts (app push, SMS), newest first. */
  async deliveryEvents(id: string, params: ListParams = {}, options?: RequestOptions): Promise<Page<DeliveryEvent>> {
    return toPage(
      await this.page<DeliveryEvent>(`/messages/${encodeURIComponent(id)}/delivery-events`, params, options),
    );
  }

  private page<T>(path: string, params: ListParams, options?: RequestOptions): Promise<WirePage<T>> {
    return this.http.request<WirePage<T>>({
      method: "GET",
      path,
      query: { limit: params.limit, cursor: params.cursor },
      options,
    });
  }

  private async *iterate<T>(path: string, params: Omit<ListParams, "cursor">, options?: RequestOptions) {
    let cursor: string | undefined;
    do {
      const page = await this.page<T>(path, { ...params, ...(cursor ? { cursor } : {}) }, options);
      yield* page.data;
      cursor = page.nextCursor ?? undefined;
    } while (cursor);
  }
}
