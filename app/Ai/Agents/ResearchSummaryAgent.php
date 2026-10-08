<?php

namespace App\Ai\Agents;

/**
 * Summarises research input (notes, interviews, competitor data) into findings.
 */
class ResearchSummaryAgent extends ActionAgent
{
    protected function role(): string
    {
        return 'You are a research analyst. Summarise the research input into key findings, patterns and open questions. Quote the source for each finding.';
    }
}
