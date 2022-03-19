<?php

namespace Monolyth\Cliff;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
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

