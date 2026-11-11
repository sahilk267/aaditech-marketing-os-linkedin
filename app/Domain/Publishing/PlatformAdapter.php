<?php

namespace App\Domain\Publishing;

use App\Models\ContentItem;
use App\Models\SocialAccount;

/** Every platform adapter must be explicitly authorized and use official APIs. */
interface PlatformAdapter
{
    public function publish(ContentItem $content, SocialAccount $account): array;
}
