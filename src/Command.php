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
        $annotations = new Annotations($reflection);
        if (isset($annotations['preload'])) {
            if (!is_array($annotations['preload'])) {
                $annotations['preload'] = [$annotations['preload']];
            }
            foreach ($annotations['preload'] as $preload) {
                require_once getcwd()."/$preload";
            }
        }
        $defaults = $reflection->getDefaultProperties();
        $usedAliases = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC) as $property) {
            $annotations = new Annotations($property);
            if (isset($annotations['alias'])) {
                if (strlen($annotations['alias']) == 1) {
                    throw new DomainException("Aliases must be one-letter shorthand codes, {$annotations['alias']} given for ".$property->getName());
                }
                $short = $annotations['alias'];
            } else {
                $short = substr($property->getName(), 0, 1);
            }
            if (in_array($short, $usedAliases)) {
                // Attempt fallback to uppercase variant
                $short = strtoupper($short);
            }
            if (in_array($short, $usedAliases)) {
                $short = null;
            } else {
                $usedAliases[] = $short;
            }
            if (strlen($property->getName()) == 1) {
                $long = null;
            } else {
                $long = self::toFlagName($property->getName());
            }
            $type = gettype($property->getValue($this));
            $optional = $type === 'boolean'
                ? GetOpt::NO_ARGUMENT
                : (isset($defaults[$property->getName()]) ? GetOpt::OPTIONAL_ARGUMENT : GetOpt::REQUIRED_ARGUMENT);
            $option = new Option($short, $long, $optional);
            self::$__optionList[$long ?? $short] = $option;
        }
        $getopt->addOptions(self::$__optionList);
        $getopt->process();
        foreach ($getopt->getOptions() as $name => $value) {
            $name = self::toPropertyName($name);
            if (!property_exists($this, $name)) {
                continue;
            }
            $type = gettype($this->$name);
            switch ($type) {
                case 'boolean':
                    $this->$name = !$this->$name;
                    break;
                default:
                    if (gettype($value) === 'string') {
                        $this->$name = $value;
                    } elseif ($type === 'string' && !strlen($this->$name)) {
                        $this->$name = null;
                    }
                    break;
            }
        }
        if ($help = $getopt->getOption('help')) {
            switch ($this->help) {
                case '*':
                    $doccomment = $reflection->getCleanedDoccomment();
                    fwrite(STDOUT, "\n$doccomment\n\n");
                    fwrite(STDOUT, "[OPTIONS] can be any of:\n\n");
                    fwrite(STDOUT, optionList($this));
                    fwrite(STDOUT, "\nCall with -hOPTION or --help=OPTION for option-specific documentation.\n\n");
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
     *  both `/` and Laravel-style `:` as separators (instead of PHP's clumsy
     * `\` namespace separator).
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

