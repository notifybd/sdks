import {
  ApiConnectionError,
  ApiTimeoutError,
  errorForStatus,
  type ErrorDetail,
} from "./errors.js";
import type { RequestOptions } from "./types.js";
import { VERSION } from "./version.js";

export type FetchLike = (input: string, init?: RequestInit) => Promise<Response>;

export interface HttpConfig {
  apiKey: string;
  baseUrl: string;
  timeoutMs: number;
  maxRetries: number;
  fetch: FetchLike;
  appInfo?: { name: string; version?: string } | undefined;
}

interface ErrorBody {
  error?: { code?: string; message?: string; requestId?: string; details?: ErrorDetail[] };
}

const RETRY_STATUSES = new Set([408, 429, 500, 502, 503, 504]);
const MAX_BACKOFF_MS = 8_000;
const BASE_BACKOFF_MS = 500;

export interface HttpRequest {
  method: "GET" | "POST" | "PUT" | "DELETE";
  path: string;
  query?: Record<string, string | number | undefined>;
  body?: unknown;
  headers?: Record<string, string>;
  /** Retrying a non-idempotent request is only safe when the server can deduplicate it. */
  retryable?: boolean;
  options?: RequestOptions | undefined;
}

export class HttpClient {
  constructor(private readonly config: HttpConfig) {}

  async request<T>(req: HttpRequest): Promise<T> {
    const url = this.url(req.path, req.query);
    const maxRetries = req.options?.maxRetries ?? this.config.maxRetries;
    const retryable = req.retryable ?? (req.method === "GET" || req.method === "DELETE");

    for (let attempt = 0; ; attempt++) {
      let response: Response;
      try {
        response = await this.send(url, req);
      } catch (error) {
        const callerAborted = req.options?.signal?.aborted === true;
        if (error instanceof ApiConnectionError && retryable && attempt < maxRetries && !callerAborted) {
          await sleep(backoff(attempt));
          continue;
        }
        throw error;
      }

      if (response.ok) {
        if (response.status === 204) return undefined as T;
        const text = await response.text();
        return (text ? JSON.parse(text) : null) as T;
      }

      if (retryable && attempt < maxRetries && RETRY_STATUSES.has(response.status)) {
        await sleep(retryDelay(response, attempt));
        continue;
      }
      throw await toApiError(response);
    }
  }

  private async send(url: string, req: HttpRequest): Promise<Response> {
    const timeoutMs = req.options?.timeoutMs ?? this.config.timeoutMs;
    const timeout = AbortSignal.timeout(timeoutMs);
    const signal = req.options?.signal ? AbortSignal.any([req.options.signal, timeout]) : timeout;
    try {
      return await this.config.fetch(url, {
        method: req.method,
        headers: this.headers(req),
        ...(req.body === undefined ? {} : { body: JSON.stringify(req.body) }),
        signal,
      });
    } catch (error) {
      if (req.options?.signal?.aborted) {
        throw new ApiConnectionError("The request was aborted", { cause: error });
      }
      if (timeout.aborted) {
        throw new ApiTimeoutError(`The request timed out after ${timeoutMs} ms`, { cause: error });
      }
      throw new ApiConnectionError(
        `Could not reach the Notify API: ${error instanceof Error ? error.message : String(error)}`,
        { cause: error },
      );
    }
  }

  private headers(req: HttpRequest): Record<string, string> {
    const app = this.config.appInfo;
    const agent = `notify-node/${VERSION}${app ? ` ${app.name}${app.version ? `/${app.version}` : ""}` : ""}`;
    return {
      Authorization: `Bearer ${this.config.apiKey}`,
      Accept: "application/json",
      "User-Agent": agent,
      ...(req.body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...req.headers,
    };
  }

  private url(path: string, query?: HttpRequest["query"]): string {
    const url = new URL(this.config.baseUrl.replace(/\/+$/, "") + path);
    for (const [key, value] of Object.entries(query ?? {})) {
      if (value !== undefined) url.searchParams.set(key, String(value));
    }
    return url.toString();
  }
}

async function toApiError(response: Response) {
  const body = (await response.json().catch(() => null)) as ErrorBody | null;
  const retryAfter = Number(response.headers.get("retry-after"));
  return errorForStatus(
    response.status,
    body?.error?.code ?? "UNKNOWN",
    body?.error?.message ?? `Request failed with status ${response.status}`,
    {
      requestId: body?.error?.requestId ?? response.headers.get("x-request-id") ?? undefined,
      details: body?.error?.details,
      retryAfterSeconds: Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : undefined,
    },
  );
}

function backoff(attempt: number): number {
  const ceiling = Math.min(MAX_BACKOFF_MS, BASE_BACKOFF_MS * 2 ** attempt);
  return ceiling / 2 + Math.random() * (ceiling / 2);
}

function retryDelay(response: Response, attempt: number): number {
  const seconds = Number(response.headers.get("retry-after"));
  if (Number.isFinite(seconds) && seconds > 0) {
    return Math.min(seconds * 1000, 30_000);
  }
  return backoff(attempt);
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
