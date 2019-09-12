<?php

namespace Monolyth\Cliff;

use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\ArgumentException\Unexpected;
use Throwable;
use Monomelodies\Reflex\ReflectionObject;
use Monomelodies\Reflex\ReflectionProperty;
use zpt\anno\Annotations;

/**
 * Abstract base command all your custom commands should extend.
 */
abstract class Command
{
    /**
     * @var string
     *
     * The built-in `-h[OPTION]` or `--help[=OPTION]` command displays detailed
     * information about an option.
     */
    public $help = '*';

    /** @var array */
    private static $__optionList = [];

    /**
     * @return void
     */
    public function __construct()
    {
        self::$__optionList = [];
        $getopt = new GetOpt;
        $reflection = new ReflectionObject($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC) as $property) {
            $annotations = new Annotations($property);
            if (isset($annotations['alias'])) {
                $short = $annotations['alias'];
            } else {
                $short = substr($property->getName(), 0, 1);
            }
            if ($getopt->getOption($short)) {
                $short = null;
            }
            if (strlen($property->getName()) == 1) {
                $long = null;
            } else {
                $long = self::toFlagName($property->getName());
            }
            $type = gettype($property->getValue($this));
            $optional = $type === 'boolean'
                ? GetOpt::NO_ARGUMENT
                : ($type !== 'NULL' ? GetOpt::OPTIONAL_ARGUMENT : GetOpt::REQUIRED_ARGUMENT);
            $option = new Option($short, $long, $optional);
            if ($long) {
                self::$__optionList[$long] = $option;
            } else {
                self::$__optionList[$short] = $option;
            }
        }
        $getopt->addOptions(self::getOptionList());
        $getopt->process();
        foreach ($getopt->getOptions() as $name => $value) {
            $name = self::toPropertyName($name);
            if (!property_exists($this, $name)) {
                continue;
            }
            $this->$name = gettype($this->$name) === 'boolean' ? (bool)$value : $value;
        }
        if ($help = $getopt->getOption('help')) {
            switch ($this->help) {
                case '1':
                    $doccomment = $reflection->getCleanedDoccomment();
                    fwrite(STDOUT, "\n$doccomment\n\n");
                    exit(0);
                default:
                    foreach (self::$__optionList as $option) {
                        if ($option->getShort() == $help || $option->getLong() == $help) {
                            $realName = $option->getLong() ?: $option->getShort();
                            break;
                        }
                    }
                    if (!isset($realName)) {
                        throw new Unexpected(sprintf(GetOpt::translate('option-unknown'), $help));
                    } else {
                        $realName = self::toPropertyName($realName);
                    }
                    // We know this exists, or GetOpt will have complained earlier.
                    $property = new ReflectionProperty($this, $realName);
                    $doccomment = $property->getCleanedDoccomment();
                    fwrite(STDOUT, "\n$doccomment\n\n");
                    exit(0);
            }
        }
    }

    /**
     * @param string $name Command name as passed on the CLI. Cliff supports
     *  both `/` as Laravel-style `:` as separators (instead of PHP's clumsy `\`
     *  namespace separator).
     * @return string
     */
    public static function toPhpName(string $name) : string
    {
        $parts = preg_split('@[:/]@', strtolower($name));
        array_walk($parts, function (&$part) : void {
            $part = ucfirst($part);
        });
        return implode('\\', $parts).'\Command';
    }

    /**
     * Returns the full list of available options.
     *
     * @return array Array of `GetOpt\Option` objects.
     */
    public static function getOptionList() : array
    {
        return self::$__optionList;
    }

    /**
     * @param string $name
     * @return string
     */
    private static function toFlagName(string $name) : string
    {
        return preg_replace_callback('@([A-Z])@', function ($match) {
            return strtolower("_{$match[1]}");
        }, $name);
    }

    /**
     * @param string $name
     * @return string
     */
    private static function toPropertyName(string $name) : string
    {
        return preg_replace_callback('@_([a-z])@', function ($match) {
            return strtoupper($match[1]);
        }, $name);
    }
}

