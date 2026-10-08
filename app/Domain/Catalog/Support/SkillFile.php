<?php

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Data\SkillFileData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a SKILL.md and enforces the authoring rules (docs/MCP_AND_SKILLS.md §5).
 */
class SkillFile
{
    public const int MAX_NAME_LENGTH = 64;

    public const int MAX_DESCRIPTION_LENGTH = 200;

    public const int MAX_BODY_LINES = 500;

    /**
     * @return list<string> SKILL.md paths, one per skill folder
     */
    public static function find(string $directory): array
    {
        return array_values(File::glob("{$directory}/*/SKILL.md"));
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function read(string $file): SkillFileData
    {
        $folder = basename(dirname($file));
        $contents = File::get($file);
        [$front, $body] = self::split($contents);
        $name = is_string($front['name'] ?? null) ? $front['name'] : '';

        if ($name !== $folder || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) || strlen($name) > self::MAX_NAME_LENGTH || preg_match('/claude|anthropic/', $name)) {
            throw new InvalidArgumentException("skills/{$folder}: name [{$name}] must equal the folder, be kebab-case, ≤64 chars, without claude/anthropic");
        }

        $description = is_string($front['description'] ?? null) ? $front['description'] : '';

        if ($description === '' || mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new InvalidArgumentException("skills/{$folder}: description must be 1–200 chars");
        }

        if (substr_count($body, "\n") >= self::MAX_BODY_LINES) {
            throw new InvalidArgumentException("skills/{$folder}: body must be under 500 lines; move details to references/");
        }

        $metadata = is_array($front['metadata'] ?? null) ? $front['metadata'] : [];

        return new SkillFileData(
            name: $name,
            description: $description,
            title: is_string($metadata['title'] ?? null) ? $metadata['title'] : Str::headline($name),
            version: (string) ($metadata['version'] ?? '1.0.0'),
            inPlugin: (bool) ($metadata['in_plugin'] ?? true),
            inAppAgents: (bool) ($metadata['in_app_agents'] ?? false),
            directory: dirname($file),
            contentHash: hash('sha256', $contents),
        );
    }

    /**
     * YAML frontmatter between the leading `---` fences, and the body after it.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private static function split(string $contents): array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $contents, $matches)) {
            return [[], $contents];
        }

        return [(array) Yaml::parse($matches[1]), $matches[2]];
    }
}
