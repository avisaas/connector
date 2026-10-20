<?php

namespace AviSaaS\Connector\Classes;

enum KeyExchangeKind
{
    case Connected;
    case DomainMismatch;
    case InvalidKey;
    case Throttled;
    case Unreachable;
}

final class KeyExchangeResult
{
    private function __construct(
        private readonly KeyExchangeKind $kind,
        private readonly ?string $credential = null,
        private readonly ?string $repositoryUrl = null,
        private readonly ?string $domain = null,
        private readonly ?int $retryAfterMinutes = null,
    ) {}

    public static function fromResponse(int $status, mixed $decodedBody, ?string $retryAfterHeader): self
    {
        return match ($status) {
            200 => self::fromSuccessBody($decodedBody),
            403 => self::fromForbiddenBody($decodedBody),
            429 => new self(KeyExchangeKind::Throttled, retryAfterMinutes: self::retryAfterMinutes($decodedBody, $retryAfterHeader)),
            default => self::unreachable(),
        };
    }

    public static function unreachable(): self
    {
        return new self(KeyExchangeKind::Unreachable);
    }

    public function isConnected(): bool
    {
        return $this->kind === KeyExchangeKind::Connected;
    }

    public function isDomainMismatch(): bool
    {
        return $this->kind === KeyExchangeKind::DomainMismatch;
    }

    public function isInvalidKey(): bool
    {
        return $this->kind === KeyExchangeKind::InvalidKey;
    }

    public function isThrottled(): bool
    {
        return $this->kind === KeyExchangeKind::Throttled;
    }

    public function isUnreachable(): bool
    {
        return $this->kind === KeyExchangeKind::Unreachable;
    }

    public function credential(): ?string
    {
        return $this->credential;
    }

    public function repositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    public function domain(): ?string
    {
        return $this->domain;
    }

    public function message(): ?string
    {
        return match ($this->kind) {
            KeyExchangeKind::Connected => null,
            KeyExchangeKind::DomainMismatch => "This key is locked to {$this->domain}. To use it here, change the website in your avisaas account, under Licences.",
            KeyExchangeKind::InvalidKey => "We don't recognise that licence key. Check your purchase email.",
            KeyExchangeKind::Throttled => $this->throttledMessage(),
            KeyExchangeKind::Unreachable => "Couldn't reach avisaas. Try again in a few minutes.",
        };
    }

    private function throttledMessage(): string
    {
        return match ($this->retryAfterMinutes) {
            null => 'Too many attempts. Try again in a few minutes.',
            1 => 'Too many attempts. Try again in 1 minute.',
            default => "Too many attempts. Try again in {$this->retryAfterMinutes} minutes.",
        };
    }

    private static function fromSuccessBody(mixed $body): self
    {
        if (!is_array($body) || ($body['ok'] ?? null) !== true) {
            return self::unreachable();
        }

        $credential = $body['credential'] ?? null;
        $repositoryUrl = $body['repository_url'] ?? null;
        $domain = $body['domain'] ?? null;

        if (!is_string($credential) || $credential === '') {
            return self::unreachable();
        }

        if (!is_string($repositoryUrl) || !(str_starts_with($repositoryUrl, 'http://') || str_starts_with($repositoryUrl, 'https://'))) {
            return self::unreachable();
        }

        if (!is_string($domain) || $domain === '') {
            return self::unreachable();
        }

        return new self(KeyExchangeKind::Connected, credential: $credential, repositoryUrl: $repositoryUrl, domain: $domain);
    }

    private static function fromForbiddenBody(mixed $body): self
    {
        if (!is_array($body)) {
            return self::unreachable();
        }

        $reason = $body['reason'] ?? null;

        if ($reason === 'domain_mismatch') {
            $domain = $body['domain'] ?? null;

            return is_string($domain) && $domain !== ''
                ? new self(KeyExchangeKind::DomainMismatch, domain: $domain)
                : self::unreachable();
        }

        if ($reason === 'invalid_key') {
            return new self(KeyExchangeKind::InvalidKey);
        }

        return self::unreachable();
    }

    private static function retryAfterMinutes(mixed $body, ?string $retryAfterHeader): ?int
    {
        if (is_array($body) && is_int($body['retry_after'] ?? null)) {
            $seconds = $body['retry_after'];
        } elseif ($retryAfterHeader !== null && ctype_digit($retryAfterHeader)) {
            $seconds = (int) $retryAfterHeader;
        } else {
            return null;
        }

        return max(1, (int) ceil($seconds / 60));
    }
}
