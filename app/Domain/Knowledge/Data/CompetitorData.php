<?php

namespace App\Domain\Knowledge\Data;

use Carbon\CarbonImmutable;

final readonly class CompetitorData
{
    public function __construct(
        public string $name,
        public ?string $url = null,
        public ?string $pricing = null,
        public ?string $icp = null,
        public ?string $positioning = null,
        public ?string $features = null,
        public ?string $integrations = null,
        public ?string $strengths = null,
        public ?string $complaints = null,
        public ?CarbonImmutable $lastReviewedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'pricing' => $this->pricing,
            'icp' => $this->icp,
            'positioning' => $this->positioning,
            'features' => $this->features,
            'integrations' => $this->integrations,
            'strengths' => $this->strengths,
            'complaints' => $this->complaints,
            'last_reviewed_at' => $this->lastReviewedAt?->toDateString(),
        ];
    }

    public function title(): string
    {
        return 'Competitor: '.$this->name;
    }

    /**
     * Markdown body of the mirrored knowledge document.
     */
    public function toMarkdown(): string
    {
        $meta = array_filter([
            'Website' => $this->url,
            'Last reviewed' => $this->lastReviewedAt?->toDateString(),
        ]);

        return MarkdownSections::render($this->title(), $meta, [
            'Pricing' => $this->pricing,
            'ICP' => $this->icp,
            'Positioning' => $this->positioning,
            'Features' => $this->features,
            'Integrations' => $this->integrations,
            'Strengths' => $this->strengths,
            'Complaints' => $this->complaints,
        ]);
    }
}
