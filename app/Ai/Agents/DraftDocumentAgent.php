<?php

namespace App\Ai\Agents;

/**
 * Drafts founder documents (ICP, positioning, messaging, briefs …) with the drafting model.
 */
class DraftDocumentAgent extends ActionAgent
{
    protected function role(): string
    {
        return 'You are a startup strategist who drafts founder documents. Produce a complete first draft the founder can edit, with headings and concrete statements.';
    }

    public function model(): string
    {
        return (string) config('ai.models.drafting');
    }
}
