<?php

namespace App\Domain\Activity\Data;

use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Models\User;

final readonly class Actor
{
    public function __construct(
        public ActorType $type,
        public ?string $id,
        public ActivityChannel $channel,
        public ?string $clientName = null,
    ) {}

    public static function user(User $user, ActivityChannel $channel = ActivityChannel::Web): self
    {
        return new self(ActorType::User, $user->id, $channel);
    }

    public static function agent(User $user, string $clientName): self
    {
        return new self(ActorType::Agent, $user->id, ActivityChannel::Mcp, $clientName);
    }

    public static function appAi(User $user): self
    {
        return new self(ActorType::Agent, $user->id, ActivityChannel::Queue, 'Founder OS AI');
    }

    public static function system(ActivityChannel $channel = ActivityChannel::Queue): self
    {
        return new self(ActorType::System, null, $channel);
    }

    public static function current(): self
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return self::user($user);
        }

        return self::system(app()->runningInConsole() ? ActivityChannel::Cli : ActivityChannel::Queue);
    }
}
