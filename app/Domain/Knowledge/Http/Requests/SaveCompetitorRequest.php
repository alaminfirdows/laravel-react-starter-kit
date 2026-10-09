<?php

namespace App\Domain\Knowledge\Http\Requests;

use App\Domain\Knowledge\Data\CompetitorData;
use App\Domain\Project\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class SaveCompetitorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'pricing' => $text,
            'icp' => $text,
            'positioning' => $text,
            'features' => $text,
            'integrations' => $text,
            'strengths' => $text,
            'complaints' => $text,
            'last_reviewed_at' => ['nullable', 'date'],
        ];
    }

    public function toData(): CompetitorData
    {
        return new CompetitorData(
            name: $this->string('name')->toString(),
            url: $this->input('url'),
            pricing: $this->input('pricing'),
            icp: $this->input('icp'),
            positioning: $this->input('positioning'),
            features: $this->input('features'),
            integrations: $this->input('integrations'),
            strengths: $this->input('strengths'),
            complaints: $this->input('complaints'),
            lastReviewedAt: $this->filled('last_reviewed_at') ? CarbonImmutable::parse($this->string('last_reviewed_at')->toString()) : null,
        );
    }
}
