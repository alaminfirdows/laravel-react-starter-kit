<?php

namespace App\Domain\Knowledge\Http\Requests;

use App\Domain\Knowledge\Data\InterviewData;
use App\Domain\Project\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class SaveInterviewRequest extends FormRequest
{
    public const int MAX_TEXT = 20_000;

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
        $text = ['nullable', 'string', 'max:'.self::MAX_TEXT];

        return [
            'person' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'interviewed_on' => ['nullable', 'date'],
            'problem' => $text,
            'current_solution' => $text,
            'pain' => $text,
            'desired_outcome' => $text,
            'objections' => $text,
            'quotes' => $text,
            'feature_requests' => $text,
        ];
    }

    public function toData(): InterviewData
    {
        return new InterviewData(
            person: $this->string('person')->toString(),
            company: $this->input('company'),
            role: $this->input('role'),
            interviewedOn: $this->filled('interviewed_on') ? CarbonImmutable::parse($this->string('interviewed_on')->toString()) : null,
            problem: $this->input('problem'),
            currentSolution: $this->input('current_solution'),
            pain: $this->input('pain'),
            desiredOutcome: $this->input('desired_outcome'),
            objections: $this->input('objections'),
            quotes: $this->input('quotes'),
            featureRequests: $this->input('feature_requests'),
        );
    }
}
