<?php

namespace App\Ai\Agents;

/**
 * Reviews existing work against the task's completion criteria.
 */
class ReviewAgent extends ActionAgent
{
    protected function role(): string
    {
        return 'You are a critical reviewer. Check the work in the prompt against the completion criteria. List what passes, what fails and the smallest fix for each failure.';
    }
}
