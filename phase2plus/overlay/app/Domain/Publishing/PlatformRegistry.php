<?php

namespace App\Domain\Publishing;

class PlatformRegistry
{
    public function __construct(private OfficialLinkedInAdapter $linkedIn,private ManualOnlyAdapter $manual) { }

    public function forPlatform(string $platform): PlatformAdapter
    {
        return $platform==='linkedin' ? $this->linkedIn : $this->manual;
    }
}
