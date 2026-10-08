<?php

namespace App\Domain\Prompt\Data;

use App\Domain\Prompt\Enums\DeepLinkTarget;

/**
 * `url` is null when the encoded launcher is too long; the UI then falls back to copy.
 */
final readonly class DeepLinkData
{
    public function __construct(
        public DeepLinkTarget $target,
        public string $launcher,
        public ?string $url,
    ) {}

    /**
     * @return array{target: string, launcher: string, url: string|null}
     */
    public function toArray(): array
    {
        return ['target' => $this->target->value, 'launcher' => $this->launcher, 'url' => $this->url];
    }
}
