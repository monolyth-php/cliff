<?php

namespace Monolyth\Cliff\Demo;

use Monolyth\Cliff;

/**
 * Demo command for Cliff.
 *
 * It showcases a required, optional and empty option and outputs the input.
 */
class Command extends Cliff\Command
{
    /**
     * @var string
     *
     * This option is required, as it doesn't have a default value.
     */
    public $requiredOption;
    /**
     * @var string
     *
     * This option is optional, as can be seen by the default value.
     */
    public $optionalOption = 'dummy';
    /**
     * @var bool
     *
     * This option is empty; it's a boolean (yes/no are our only options).
     */
    public $emptyOption = false;

    /**
     * To run the demo command, exactly one argument needs to be passed. It can
     * be any string value, it is simply outputted.
     */
    public function __invoke(string $name)
    {
        fwrite(STDOUT, <<<EOT
Great! You called this command for `$name`.

EOT
        );
        if (isset($this->requiredOption)) {
            fwrite(STDOUT, <<<EOT
Our required option was set to `{$this->requiredOption}`.

EOT
            );
        } else {
            fwrite(STDOUT, <<<EOT
The required option was not set.

EOT
            );
        }
        if ($this->optionalOption != 'dummy') {
            fwrite(STDOUT, <<<EOT
Our optional option was overriden with `{$this->optionalOption}`.

EOT
            );
        }
        if ($this->emptyOption) {
            fwrite(STDOUT, "The empty option was also set.\n");
        }
    }
}

