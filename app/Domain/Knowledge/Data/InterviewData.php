<?php

namespace App\Domain\Knowledge\Data;

use Carbon\CarbonImmutable;

final readonly class InterviewData
{
    public function __construct(
        public string $person,
        public ?string $company = null,
        public ?string $role = null,
        public ?CarbonImmutable $interviewedOn = null,
        public ?string $problem = null,
        public ?string $currentSolution = null,
        public ?string $pain = null,
        public ?string $desiredOutcome = null,
        public ?string $objections = null,
        public ?string $quotes = null,
        public ?string $featureRequests = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'person' => $this->person,
            'company' => $this->company,
            'role' => $this->role,
            'interviewed_on' => $this->interviewedOn?->toDateString(),
            'problem' => $this->problem,
            'current_solution' => $this->currentSolution,
            'pain' => $this->pain,
            'desired_outcome' => $this->desiredOutcome,
            'objections' => $this->objections,
            'quotes' => $this->quotes,
            'feature_requests' => $this->featureRequests,
        ];
    }

    public function title(): string
    {
        return 'Interview: '.$this->person.($this->company ? " ({$this->company})" : '');
    }

    /**
     * Markdown body of the mirrored knowledge document.
     */
    public function toMarkdown(): string
    {
        $meta = array_filter([
            'Role' => $this->role,
            'Company' => $this->company,
            'Date' => $this->interviewedOn?->toDateString(),
        ]);

        return MarkdownSections::render($this->title(), $meta, [
            'Problem' => $this->problem,
            'Current solution' => $this->currentSolution,
            'Pain' => $this->pain,
            'Desired outcome' => $this->desiredOutcome,
            'Objections' => $this->objections,
            'Quotes' => $this->quotes,
            'Feature requests' => $this->featureRequests,
        ]);
    }
}
