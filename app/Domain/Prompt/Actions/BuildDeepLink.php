<?php

namespace App\Domain\Prompt\Actions;

use App\Domain\Prompt\Data\DeepLinkData;
use App\Domain\Prompt\Enums\DeepLinkTarget;
use App\Domain\Task\Models\TaskAction;

/**
 * `claude://` deep link that prefills the launcher prompt. The user still presses Send.
 */
class BuildDeepLink
{
    /** Far below Claude's ~14k `q` limit. */
    public const int MAX_URL_LENGTH = 8000;

    public function __construct(private RenderLauncherPrompt $launcher) {}

    public function handle(TaskAction $action): DeepLinkData
    {
        return $this->link(DeepLinkTarget::for($action), $this->launcher->handle($action));
    }

    public function link(DeepLinkTarget $target, string $launcher): DeepLinkData
    {
        $url = $target->baseUrl().rawurlencode($launcher);

        return new DeepLinkData($target, $launcher, strlen($url) <= self::MAX_URL_LENGTH ? $url : null);
    }
}
