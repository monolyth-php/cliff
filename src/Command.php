<?php

namespace Monolyth\Cliff;

use GetOpt\GetOpt;
use GetOpt\Option;
use Throwable;
use ReflectionObject;
use ReflectionProperty;
use zpt\anno\Annotations;

abstract class Command
{
    public function __construct()
    {
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
            $options[] = new Option($short, $long, $optional);
        }
        $getopt->addOptions($options);
        $getopt->process();
        foreach ($getopt->getOptions() as $name => $value) {
            $name = self::toPropertyName($name);
            if (!property_exists($this, $name)) {
                continue;
            }
            $this->$name = gettype($this->$name) === 'boolean' ? (bool)$value : $value;
        }
    }

    public static function toPhpName(string $name) : string
    {
        $parts = preg_split('@[:/]@', strtolower($name));
        array_walk($parts, function (&$part) : void {
            $part = ucfirst($part);
        });
        return implode('\\', $parts).'\Command';
    }

    public static function error(Throwable $e) : void
    {
        // for now:
        var_dump($e->getMessage());
    }

    private static function toFlagName(string $name) : string
    {
        return preg_replace_callback('@([A-Z])@', function ($match) {
            return strtolower("_{$match[1]}");
        }, $name);
    }

    private static function toPropertyName(string $name) : string
    {
        return preg_replace_callback('@_([a-z])@', function ($match) {
            return strtoupper($match[1]);
        }, $name);
    }
}

