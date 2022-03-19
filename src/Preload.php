<?php

namespace Monolyth\Cliff;

#[\Attribute]
class Preload
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function __toString() : string
    {
        return $this->file;
    }
}

