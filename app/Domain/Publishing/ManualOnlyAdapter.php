<?php

namespace App\Domain\Publishing;

use App\Models\ContentItem;
use App\Models\SocialAccount;
use Illuminate\Validation\ValidationException;

/** Non-authorized channels never try browser/session automation. */
class ManualOnlyAdapter implements PlatformAdapter
{
    public function publish(ContentItem $content, SocialAccount $account): array
    {
        throw ValidationException::withMessages(['platform'=>'No approved official publishing API adapter; use manual_ready']);
    }
}
