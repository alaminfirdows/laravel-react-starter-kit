<?php

namespace App\Domain\Project\Http\Requests;

use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Queries\AvailablePacks;
use App\Domain\Project\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class AddPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()->can('update', $project);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pack' => ['required', 'string'],
        ];
    }

    /**
     * The chosen pack, if the project may add it.
     */
    public function pack(AvailablePacks $availablePacks): Pack
    {
        $project = $this->route('project');
        $pack = $project instanceof Project
            ? $availablePacks->query($project)->where('key', $this->string('pack')->value())->first()
            : null;

        if ($pack === null) {
            throw ValidationException::withMessages(['pack' => __('This pack is not available for the project.')]);
        }

        return $pack;
    }
}
