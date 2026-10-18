<?php

namespace App\Services\Processor;

final class ProcessorResult
{
    private function __construct(
        public readonly bool $approved,
        public readonly ?string $declineCode = null,
    ) {}

    public static function approved(): self
    {
        return new self(true);
    }

    public static function declined(string $code): self
    {
        return new self(false, $code);
    }
}
