<?php

namespace Monolyth\Cliff\Demo;

use Monolyth\Cliff;

/**
 * Demo command for Cliff.
 */
class Command extends Cliff\Command
{
    /**
     * @var string
     */
    public $requiredProperty;
    /**
     * @var string
     */
    public $optionalProperty = 'dummy';
    /**
     * @var bool
     */
    public $emptyProperty = false;

    public function __invoke(string $name)
    {
        fwrite(STDOUT, <<<EOT
Great! You called this command for `$name`.

EOT
        );
        fwrite(STDOUT, <<<EOT
Our required property was set to `{$this->requiredProperty}`.

EOT
        );
        if ($this->optionalProperty != 'dummy') {
            fwrite(STDOUT, <<<EOT
Our optional property was overriden with `{$this->optionalProperty}`.

EOT
            );
        }
        if ($this->emptyProperty) {
            fwrite(STDOUT, "The empty property was also set.\n");
        }
    }
}

