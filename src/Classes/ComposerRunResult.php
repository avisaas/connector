<?php

namespace AviSaaS\Connector\Classes;

enum ComposerRunKind
{
    case Done;
    case Busy;
    case Failed;
}

final class ComposerRunResult
{
    private function __construct(
        public readonly ComposerRunKind $kind,
        public readonly bool $restored = false,
        public readonly ?string $reason = null,
        public readonly bool $notStarted = false,
    ) {}

    public static function done(): self
    {
        return new self(ComposerRunKind::Done);
    }

    public static function busy(): self
    {
        return new self(ComposerRunKind::Busy);
    }

    public static function failed(string $reason, bool $restored): self
    {
        return new self(ComposerRunKind::Failed, $restored, $reason);
    }

    public static function notStarted(string $reason): self
    {
        return new self(ComposerRunKind::Failed, restored: true, reason: $reason, notStarted: true);
    }
}
