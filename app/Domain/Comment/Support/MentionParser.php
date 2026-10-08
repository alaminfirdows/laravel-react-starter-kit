<?php

namespace App\Domain\Comment\Support;

use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds `@handle` mentions. A handle is a member's name without spaces or their email name, any case.
 * Handles that match no workspace member stay plain text.
 */
class MentionParser
{
    /**
     * @return list<string>
     */
    public function handles(string $body): array
    {
        preg_match_all('/(?<![\w@])@([\w.\-]+)/u', $body, $matches);

        return array_values(array_unique(array_map(
            fn (string $handle): string => Str::lower(rtrim($handle, '.-')),
            $matches[1],
        )));
    }

    /**
     * @return Collection<int, User>
     */
    public function members(Workspace $workspace, string $body): Collection
    {
        $handles = $this->handles($body);

        if ($handles === []) {
            return new Collection;
        }

        return $workspace->members()->get()
            ->filter(fn (User $user): bool => array_intersect($this->handlesFor($user), $handles) !== [])
            ->values()
            ->toBase();
    }

    /**
     * @return list<string>
     */
    public function handlesFor(User $user): array
    {
        return [
            Str::lower((string) preg_replace('/\s+/u', '', $user->name)),
            Str::lower(Str::before($user->email, '@')),
        ];
    }
}
