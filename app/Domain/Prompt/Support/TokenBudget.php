<?php

namespace App\Domain\Prompt\Support;

use Illuminate\Support\Str;

/**
 * Rough token budget (≈ 4 characters per token) for prompts we render ourselves.
 */
final readonly class TokenBudget
{
    public const int CHARS_PER_TOKEN = 4;

    public const string TRUNCATION_MARKER = "\n…[truncated]";

    public function __construct(public int $maxTokens) {}

    public static function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }

    public function maxChars(): int
    {
        return $this->maxTokens * self::CHARS_PER_TOKEN;
    }

    public function fits(string $text): bool
    {
        return mb_strlen($text) <= $this->maxChars();
    }

    public function trim(string $text): string
    {
        if ($this->fits($text)) {
            return $text;
        }

        return Str::substr($text, 0, $this->maxChars() - mb_strlen(self::TRUNCATION_MARKER)).self::TRUNCATION_MARKER;
    }
}
