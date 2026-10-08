<?php

namespace App\Domain\Project\Http\Requests;

use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectSetupRequest extends FormRequest
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
        return $this->step()->rules();
    }

    public function step(): ProjectSetupStep
    {
        $step = $this->route('step');

        return $step instanceof ProjectSetupStep ? $step : ProjectSetupStep::from((string) $step);
    }

    /**
     * Empty strings from the form arrive as null (ConvertEmptyStringsToNull).
     */
    protected function prepareForValidation(): void
    {
        if ($this->step() === ProjectSetupStep::Market && is_string($this->input('primary_market'))) {
            $this->merge(['primary_market' => strtoupper(trim($this->input('primary_market')))]);
        }
    }
}
