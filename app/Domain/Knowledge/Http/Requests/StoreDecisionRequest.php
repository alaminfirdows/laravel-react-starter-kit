<?php

namespace App\Domain\Knowledge\Http\Requests;

use App\Domain\Knowledge\Data\DecisionData;
use App\Domain\Project\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('update', $project);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'decision_md' => ['required', 'string', 'max:20000'],
            'rationale_md' => ['nullable', 'string', 'max:20000'],
            'revisit_on' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function toData(): DecisionData
    {
        return new DecisionData(
            title: $this->string('title')->toString(),
            decisionMd: $this->string('decision_md')->toString(),
            rationaleMd: $this->input('rationale_md'),
            revisitOn: $this->filled('revisit_on') ? CarbonImmutable::parse($this->string('revisit_on')->toString()) : null,
        );
    }
}
