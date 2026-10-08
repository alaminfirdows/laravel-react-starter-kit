<?php

namespace App\Domain\Catalog\Data;

final readonly class PluginBuildData
{
    /**
     * @param  list<string>  $skills  skill names in the plugin
     */
    public function __construct(
        public string $directory,
        public string $zipPath,
        public string $version,
        public array $skills,
    ) {}
}
