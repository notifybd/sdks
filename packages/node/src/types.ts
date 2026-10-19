export type MessageType = "otp" | "transactional";
export type MessageStatus = "received" | "queued" | "sent" | "delivered" | "failed";
export type MessageChannel = "app" | "sms";
export type SenderIdStatus = "pending" | "approved" | "rejected";

export interface SendMessageParams {
  /** Recipient. Any common Bangladeshi format is accepted (`01712345678`, `+8801712345678`, ...). */
  to: string;
  /** Message text, up to 1000 characters. Marketing or promotional content is rejected. */
  body: string;
  messageType: MessageType;
  /** One of your approved sender IDs. */
  senderId: string;
  /** Your own reference (for example an order id), up to 128 characters. Returned on the message. */
  reference?: string;
}

/** The `202 Accepted` envelope for a submitted message. Delivery happens asynchronously. */
export interface SendMessageResponse {
  id: string;
  status: "accepted";
  reference: string | null;
  createdAt: string;
}

export interface Message {
  id: string;
  businessId: string;
  senderId: string;
  body: string;
  messageType: MessageType;
  status: MessageStatus;
  /** HMAC of the recipient. The number itself is never returned. */
  toE164Hash: string;
  reference: string | null;
  channel: MessageChannel | null;
  failureReason: string | null;
  createdAt: string;
  updatedAt: string;
  sentAt: string | null;
  deliveredAt: string | null;
  failedAt: string | null;
  /** First time the recipient opened it in the app; null until then. */
  readAt: string | null;
}

export interface MessageEvent {
  eventId: string;
  eventType: string;
  messageId: string;
  businessId: string;
  toE164Hash: string | null;
  channel: string | null;
  status: string;
  errorCode: string | null;
  correlationId: string;
  occurredAt: string;
}

export interface DeliveryEvent {
  eventId: string;
  eventType: string;
  messageId: string;
  businessId: string;
  channel: string | null;
  provider: string | null;
  status: string;
  errorCode: string | null;
  correlationId: string;
  occurredAt: string;
}

export interface SenderId {
  id: string;
  value: string;
  status: SenderIdStatus;
  approvedAt: string | null;
  createdAt: string;
}

export interface ListParams {
  /** 1 to 100. Defaults to 50. */
  limit?: number;
  cursor?: string;
}

export interface Page<T> {
  data: T[];
  /** Pass to `cursor` to fetch the next page; null on the last page. */
  nextCursor: string | null;
  hasMore: boolean;
}

/** Per-request overrides. */
export interface RequestOptions {
  signal?: AbortSignal;
  timeoutMs?: number;
  maxRetries?: number;
}

export interface SendMessageOptions extends RequestOptions {
  /**
   * Makes the send safe to retry: the same key with the same body returns the original result and
   * never sends twice. Generated for you when omitted, so the SDK's own retries are safe.
   */
  idempotencyKey?: string;
}

export type MessageStatusEventName = "message.delivered" | "message.failed";

/** The JSON body Notify POSTs to your webhook endpoint when a message reaches a final status. */
export interface WebhookEvent {
  eventId: string;
  eventName: MessageStatusEventName;
  eventVersion: number;
  occurredAt: string;
  correlationId: string;
  payload: {
    messageId: string;
    businessId: string;
    status: "delivered" | "failed";
    channel: MessageChannel | null;
    reason: string | null;
  };
}
