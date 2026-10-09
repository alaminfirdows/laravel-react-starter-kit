<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Catalog\Data\PromptTemplateData;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Catalog\Support\Versioning;
use Illuminate\Support\Facades\DB;

class SavePromptTemplate
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(PromptTemplateData $data, ?PromptTemplate $prompt = null): PromptTemplate
    {
        $prompt ??= new PromptTemplate;

        $created = ! $prompt->exists;

        return DB::transaction(function () use ($prompt, $data, $created): PromptTemplate {
            if (Versioning::edit($prompt, $data->toAttributes())) {
                $this->activity->record($created ? 'catalog.prompt_created' : 'catalog.prompt_updated', $prompt, [
                    'key' => $prompt->key,
                    'version' => $prompt->version,
                ]);
            }

            return $prompt;
        });
    }
}
