<?php

namespace App\Domain\Project\Http\Resources;

use App\Domain\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wizard form values. Keys match the request fields.
 *
 * @mixin Project
 */
class ProjectSetupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'status' => $this->status,
            'logoUrl' => $this->logo_url,
            'name' => $this->name,
            'one_liner' => $this->one_liner ?? '',
            'description_md' => $this->description_md ?? '',
            'website_url' => $this->website_url ?? '',
            'business_model' => $this->business_model ?? '',
            'stage' => $this->stage ?? '',
            'industry' => $this->industry ?? '',
            'pricing_model' => $this->pricing_model ?? '',
            'primary_market' => $this->primary_market ?? '',
            'target_customer' => $this->target_customer ?? '',
            'problem_statement' => $this->problem_statement ?? '',
            'solution_summary' => $this->solution_summary ?? '',
            'goals' => $this->goals ?? [],
        ];
    }
}
