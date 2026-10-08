<?php

namespace App\Domain\Activity\Data;

use App\Domain\Activity\Enums\ActivityChannel;

/**
 * Audit view filters. `actor` is a user id or `system`.
 */
final readonly class ActivityFilters
{
    public const string SYSTEM_ACTOR = 'system';

    public function __construct(
        public ?string $actor = null,
        public ?ActivityChannel $channel = null,
        public ?string $event = null,
    ) {}

    /**
     * @return array{actor: string|null, channel: string|null, event: string|null}
     */
    public function toArray(): array
    {
        return [
            'actor' => $this->actor,
            'channel' => $this->channel?->value,
            'event' => $this->event,
        ];
    }
}
