<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Enums\PackReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCommunityPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(PackReviewStatus::class)->only([PackReviewStatus::Approved, PackReviewStatus::Rejected])],
            'note' => [Rule::requiredIf($this->input('decision') === PackReviewStatus::Rejected->value), 'nullable', 'string', 'max:2000'],
        ];
    }

    public function decision(): PackReviewStatus
    {
        return $this->enum('decision', PackReviewStatus::class) ?? PackReviewStatus::Rejected;
    }
}
