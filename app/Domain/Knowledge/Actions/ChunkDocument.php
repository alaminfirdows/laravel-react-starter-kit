<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Data\ChunkData;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Workspace\Scopes\WorkspaceScope;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a document's chunks: split by Markdown headings (`heading_path` = "H1 > H2"),
 * long sections packed into ~500-token chunks with a 50-token overlap. Embeddings come later.
 */
class ChunkDocument
{
    public const int MAX_TOKENS = 500;

    public const int OVERLAP_TOKENS = 50;

    protected const int CHARS_PER_TOKEN = 4;

    public function handle(KnowledgeDocument $document): void
    {
        $chunks = $this->split($document->body_md);

        DB::transaction(function () use ($document, $chunks): void {
            $document->chunks()->withoutGlobalScope(WorkspaceScope::class)->delete();

            foreach ($chunks as $chunk) {
                $document->chunks()->create([
                    'workspace_id' => $document->workspace_id,
                    'project_id' => $document->project_id,
                    'chunk_index' => $chunk->index,
                    'heading_path' => $chunk->headingPath,
                    'content' => $chunk->content,
                    'token_count' => $chunk->tokenCount,
                ]);
            }
        });
    }

    /**
     * @return list<ChunkData>
     */
    public function split(string $markdown): array
    {
        $chunks = [];

        foreach ($this->sections($markdown) as [$headingPath, $text]) {
            foreach ($this->pack($text) as $content) {
                $chunks[] = new ChunkData(count($chunks), $headingPath, $content, self::tokens($content));
            }
        }

        return $chunks;
    }

    public static function tokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }

    /**
     * @return list<array{0: string|null, 1: string}>
     */
    protected function sections(string $markdown): array
    {
        $sections = [];
        $headings = [];
        $lines = [];
        $inFence = false;

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = ! $inFence;
            }

            if (! $inFence && preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $line, $match) === 1) {
                $sections[] = $this->section($headings, $lines);
                $level = strlen($match[1]);
                $headings = array_filter($headings, fn (int $key): bool => $key < $level, ARRAY_FILTER_USE_KEY);
                $headings[$level] = $match[2];
                $lines = [];
            }

            $lines[] = $line;
        }

        $sections[] = $this->section($headings, $lines);

        return array_values(array_filter($sections));
    }

    /**
     * @param  array<int, string>  $headings
     * @param  list<string>  $lines
     * @return array{0: string|null, 1: string}|null
     */
    protected function section(array $headings, array $lines): ?array
    {
        $text = trim(implode("\n", $lines));

        if ($text === '') {
            return null;
        }

        return [$headings === [] ? null : mb_substr(implode(' > ', $headings), 0, 500), $text];
    }

    /**
     * @return list<string>
     */
    protected function pack(string $text): array
    {
        $limit = self::MAX_TOKENS * self::CHARS_PER_TOKEN;

        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        $chunks = [];
        $current = '';

        // Room for the overlap and the paragraph separator in every chunk.
        $unitLimit = $limit - self::OVERLAP_TOKENS * self::CHARS_PER_TOKEN - 2;

        foreach ($this->units($text, $unitLimit) as $unit) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($unit) + 2 > $limit) {
                $chunks[] = $current;
                $current = $this->overlap($current);
            }

            $current = $current === '' ? $unit : $current."\n\n".$unit;
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Paragraphs, with paragraphs over budget split by words and words over budget split hard.
     *
     * @param  positive-int  $budget
     * @return list<string>
     */
    protected function units(string $text, int $budget): array
    {
        $units = [];

        foreach (preg_split('/\n{2,}/', $text) ?: [] as $paragraph) {
            if (mb_strlen($paragraph) <= $budget) {
                $units[] = $paragraph;

                continue;
            }

            $piece = '';

            foreach (preg_split('/\s+/', $paragraph) ?: [] as $word) {
                foreach (mb_str_split($word, $budget) as $part) {
                    if ($piece !== '' && mb_strlen($piece) + mb_strlen($part) + 1 > $budget) {
                        $units[] = $piece;
                        $piece = '';
                    }

                    $piece = $piece === '' ? $part : $piece.' '.$part;
                }
            }

            if ($piece !== '') {
                $units[] = $piece;
            }
        }

        return $units;
    }

    protected function overlap(string $chunk): string
    {
        $tail = mb_substr($chunk, -self::OVERLAP_TOKENS * self::CHARS_PER_TOKEN);
        $space = mb_strpos($tail, ' ');

        return $space === false ? $tail : mb_substr($tail, $space + 1);
    }
}
