<?php

namespace App\Domain\Knowledge\Data;

/**
 * Renders a research row as Markdown: a title, a key/value list and one `##` section per filled field.
 */
final class MarkdownSections
{
    /**
     * @param  array<string, string>  $meta
     * @param  array<string, string|null>  $sections
     */
    public static function render(string $title, array $meta, array $sections): string
    {
        $parts = ["# {$title}"];

        if ($meta !== []) {
            $parts[] = collect($meta)->map(fn (string $value, string $label): string => "- **{$label}:** {$value}")->implode("\n");
        }

        foreach ($sections as $heading => $body) {
            if (filled($body)) {
                $parts[] = "## {$heading}\n\n".trim((string) $body);
            }
        }

        return implode("\n\n", $parts)."\n";
    }
}
