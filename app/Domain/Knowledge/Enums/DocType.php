<?php

namespace App\Domain\Knowledge\Enums;

use App\Support\Enums\HasOptions;

enum DocType: string
{
    use HasOptions;

    case Brief = 'brief';
    case Icp = 'icp';
    case BuyerPersona = 'buyer_persona';
    case UserPersona = 'user_persona';
    case Jtbd = 'jtbd';
    case Positioning = 'positioning';
    case Messaging = 'messaging';
    case Competitor = 'competitor';
    case Interview = 'interview';
    case Research = 'research';
    case Pricing = 'pricing';
    case Brand = 'brand';
    case Prd = 'prd';
    case Sop = 'sop';
    case PolicyDraft = 'policy_draft';
    case DecisionRecord = 'decision_record';
    case Meeting = 'meeting';
    case Other = 'other';

    /**
     * Singleton types allow one approved document per project.
     */
    public function isSingleton(): bool
    {
        return in_array($this, self::singletons(), true);
    }

    /**
     * @return list<self>
     */
    public static function singletons(): array
    {
        return [self::Brief, self::Icp, self::Positioning, self::Messaging, self::Brand];
    }

    public function label(): string
    {
        return match ($this) {
            self::Icp => 'ICP',
            self::Jtbd => 'Jobs to be done',
            self::Prd => 'PRD',
            self::Sop => 'SOP',
            default => str($this->value)->headline()->toString(),
        };
    }
}
