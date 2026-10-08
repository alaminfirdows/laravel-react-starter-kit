<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\PromptTemplateData;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Catalog\Support\Versioning;

class SavePromptTemplate
{
    public function handle(PromptTemplateData $data, ?PromptTemplate $prompt = null): PromptTemplate
    {
        $prompt ??= new PromptTemplate;

        Versioning::edit($prompt, $data->toAttributes());

        return $prompt;
    }
}
