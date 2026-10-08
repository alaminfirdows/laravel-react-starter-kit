<?php

namespace App\Domain\Catalog\Enums;

enum ResourceType: string
{
    case Article = 'article';
    case Video = 'video';
    case Template = 'template';
    case Tool = 'tool';
    case Vendor = 'vendor';
    case Affiliate = 'affiliate';
    case InternalDoc = 'internal_doc';
}
