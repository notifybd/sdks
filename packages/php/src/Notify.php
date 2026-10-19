<?php

declare(strict_types=1);

namespace NotifyBd;

use NotifyBd\Http\CurlTransport;
use NotifyBd\Http\Requestor;
use NotifyBd\Http\Transport;
use NotifyBd\Resource\Messages;
use NotifyBd\Resource\Senders;

/**
 * ```php
 * $notify = new \NotifyBd\Notify('nk_live_...');   // or set NOTIFY_API_KEY
 * $accepted = $notify->messages->send([
 *     'to' => '01712345678',
 *     'body' => 'Your code is 123456',
 *     'messageType' => 'otp',
 *     'senderId' => 'MyShop',
 * ]);
 * ```
 */
final class Notify
{
    /** Replace with the production URL before the first release (see docs/RELEASING.md). */
    public const DEFAULT_BASE_URL = 'https://api.notify.example/v1';

    public readonly Messages $messages;
    public readonly Senders $senders;

    /**
     * @param string|array{
     *     apiKey?: string,
     *     baseUrl?: string,
     *     timeout?: float,
     *     maxRetries?: int,
     *     transport?: Transport,
     *     appInfo?: array{name: string, version?: string},
     *     sleeper?: callable(int): void
     * }|null $options an API key, or an options array
     */
    public function __construct(string|array|null $options = null)
    {
        $options = is_string($options) ? ['apiKey' => $options] : ($options ?? []);

        $apiKey = $options['apiKey'] ?? (getenv('NOTIFY_API_KEY') ?: null);
        if ($apiKey === null || $apiKey === '') {
            throw new \InvalidArgumentException(
                'No API key. Pass it to the constructor or set the NOTIFY_API_KEY environment variable.',
            );
        }

        $requestor = new Requestor(
            $apiKey,
            $options['baseUrl'] ?? (getenv('NOTIFY_BASE_URL') ?: self::DEFAULT_BASE_URL),
            $options['timeout'] ?? 30.0,
            $options['maxRetries'] ?? 2,
            $options['transport'] ?? new CurlTransport(),
            $options['appInfo'] ?? null,
            $options['sleeper'] ?? null,
        );
        $this->messages = new Messages($requestor);
        $this->senders = new Senders($requestor);
    }
}
