<?php

namespace Monolyth\Cliff;

#[\Attribute]
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

