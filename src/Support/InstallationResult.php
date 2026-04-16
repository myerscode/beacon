<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Support;

class InstallationResult
{
    public const STATUS_NOTHING  = 'nothing';
    public const STATUS_REMOVED  = 'removed';
    public const STATUS_SKIPPED  = 'skipped';
    public const STATUS_SUCCESS  = 'success';

    /**
     * @param string[] $messages
     */
    public function __construct(
        public readonly string $status,
        public readonly string $summary,
        public readonly array $messages = [],
    ) {
    }

    public static function nothing(string $summary, string ...$messages): self
    {
        return new self(self::STATUS_NOTHING, $summary, $messages);
    }

    public static function removed(string $summary, string ...$messages): self
    {
        return new self(self::STATUS_REMOVED, $summary, $messages);
    }

    public static function skipped(string $summary, string ...$messages): self
    {
        return new self(self::STATUS_SKIPPED, $summary, $messages);
    }

    public static function success(string $summary, string ...$messages): self
    {
        return new self(self::STATUS_SUCCESS, $summary, $messages);
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function ok(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_SKIPPED, self::STATUS_REMOVED, self::STATUS_NOTHING], true);
    }

    /**
     * Return all messages and the summary as a single string.
     */
    public function output(): string
    {
        $lines = [...$this->messages, $this->summary];

        return implode("\n", $lines) . "\n";
    }

    public function successful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
