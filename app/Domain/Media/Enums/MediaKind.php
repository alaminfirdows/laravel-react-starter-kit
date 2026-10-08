<?php

namespace App\Domain\Media\Enums;

enum MediaKind: string
{
    case Image = 'image';
    case Document = 'document';
    case Export = 'export';
    case Screenshot = 'screenshot';
}
