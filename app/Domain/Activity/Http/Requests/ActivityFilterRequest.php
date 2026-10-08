<?php

namespace App\Domain\Activity\Http\Requests;

use App\Domain\Activity\Data\ActivityFilters;
use App\Domain\Activity\Enums\ActivityChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'string', 'max:26'],
            'channel' => ['nullable', Rule::enum(ActivityChannel::class)],
            'event' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function filters(): ActivityFilters
    {
        return new ActivityFilters(
            actor: $this->filled('actor') ? $this->string('actor')->value() : null,
            channel: $this->enum('channel', ActivityChannel::class),
            event: $this->filled('event') ? $this->string('event')->value() : null,
        );
    }
}
