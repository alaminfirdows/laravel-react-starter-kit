<?php

namespace App\Domain\Task\Data;

use App\Domain\Task\Enums\CriterionKind;

/**
 * One item of `tasks.completion_criteria`. `action` names the catalog action key the
 * criterion belongs to; without it the criterion gates the action that closes the task.
 */
final readonly class CriterionData
{
    public function __construct(
        public string $key,
        public string $label,
        public CriterionKind $kind,
        public ?string $action = null,
        public ?string $checkRef = null,
    ) {}

    /**
     * @param  array<string, mixed>  $item  `{key, label?, kind?, action?, check_ref?}`
     */
    public static function fromArray(array $item): self
    {
        $key = (string) $item['key'];

        return new self(
            key: $key,
            label: (string) ($item['label'] ?? $key),
            kind: CriterionKind::tryFrom((string) ($item['kind'] ?? '')) ?? CriterionKind::Manual,
            action: isset($item['action']) ? (string) $item['action'] : null,
            checkRef: isset($item['check_ref']) ? (string) $item['check_ref'] : null,
        );
    }
}
