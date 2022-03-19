<?php

namespace Monolyth\Cliff;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Alias
{
    private string $alias;

    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

    public function __toString() : string
    {
        return $this->alias;
    }
}

