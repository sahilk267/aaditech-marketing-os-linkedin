<?php

namespace App\Domain\Publishing;

class PlatformRegistry
{
    public function __construct(
        private OfficialLinkedInAdapter $linkedIn,
        private OfficialFacebookPageAdapter $facebook,
        private ManualOnlyAdapter $manual
    ) {}

    public function forPlatform(string $platform): PlatformAdapter
    {
        return match ($platform) {
            'linkedin' => $this->linkedIn,
            'facebook' => $this->facebook,
            default => $this->manual,
        };
    }
}
