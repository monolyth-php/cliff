<?php

namespace Monolyth\Cliff;

use GetOpt\{ GetOpt, Option, ArgumentException\Unexpected, Operand };
use Throwable;
use Monomelodies\Reflex\{ ReflectionObject, ReflectionMethod, ReflectionProperty };
use Generator;

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
    public string $help = '*';

    /** @var array */
    private array $_optionList = [];

    /** @var GetOpt\GetOpt */
    private $_getopt;

    /**
     * @param array|null $arguments Optional manual arguments.
     * @return void
     */
    public function __construct(array $arguments = null)
    {
        $getopt = new GetOpt(null, [GetOpt::SETTING_STRICT_OPTIONS => false]);
        $reflection = new ReflectionObject($this);
        $preloads = $reflection->getAttributes(Preload::class);
        if ($preloads) {
            $this->preloadDependencies(...$preloads);
        }
        $this->convertPropertiesToOptions($reflection);
        $getopt->addOptions($this->_optionList);
        foreach ($this->convertParametersToOperands($getopt) as $operand) {
            $getopt->addOperand($operand);
        }
        $getopt->process($arguments);
        foreach ($getopt->getOptions() as $name => $value) {
            $this->convertOptionToProperty($name, $value);
        }
        $this->_getopt = $getopt;
    }

    /**
     * Renders documentation, if requested.
     *
     * @return bool True if help was shown, else false.
     * @throws GetOpt\ArgumentException\Unexpected
     */
    public function showHelp() : bool
    {
        if ($help = $this->_getopt->getOption('help')) {
            switch ($this->help) {
                case '*':
                    $reflection = new ReflectionObject($this);
                    $doccomment = $reflection->getCleanedDoccomment();
                    fwrite(STDOUT, "\n$doccomment\n\n");
                    fwrite(STDOUT, "[OPTIONS] can be any of:\n\n");
                    fwrite(STDOUT, optionList($this));
                    fwrite(STDOUT, "\nCall with -hOPTION or --help=OPTION for option-specific documentation.\n\n");
                    return true;
                default:
                    foreach ($this->_optionList as $option) {
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
                    return true;
            }
        }
        return false;
    }

    /**
     * Returns the full list of available options.
     *
     * @return array Array of `GetOpt\Option` objects.
     */
    public function getOptionList() : array
    {
        return $this->_optionList;
    }

    /**
     * Returns the list of operands.
     *
     * @return array
     */
    public function getOperands() : array
    {
        return $this->_getopt->getOperands();
    }

    /**
     * Execute the command. Shorthand for calling the command's custom
     * `__invoke` method with the correct parameters.
     *
     * @return void
     */
    public function execute() : void
    {
        $this->__invoke(...$this->getOperands());
    }

    /**
     * @param string $name Command name as passed on the CLI. Cliff supports
     *  both `/` and Laravel-style `:` as separators (instead of PHP's clumsy
     * `\` namespace separator).
     * @return string
     */
    public static function toPhpName(string $name) : string
    {
        $parts = preg_split('@[:/]@', self::toPropertyName($name));
        array_walk($parts, function (&$part) : void {
            $part = ucfirst($part);
        });
        return implode('\\', $parts);
    }

    /**
     * @param Monolyth\Cliff\Preload ...$dependencies
     * @return void
     */
    private function preloadDependencies(Preload ...$dependencies) : void
    {
        foreach ($dependencies as $preload) {
            require_once getcwd()."/$preload";
        }
    }

    private function convertPropertiesToOptions(ReflectionObject $reflection) : void
    {
        $defaults = $reflection->getDefaultProperties();
        $usedAliases = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC) as $property) {
            $aliases = $property->getAttributes(Alias::class);
            if ($aliases) {
                if (strlen("{$aliases[0]}" != 1)) {
                    throw new IllegalShortHandAliasException("Aliases must be one-letter shorthand codes, {$aliases[0]} given for ".$property->getName());
                }
                $short = "{$aliases[0]}";
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
            $name = $property->getName();
            if (strlen($name) == 1) {
                $long = null;
            } else {
                $long = self::toFlagName($name);
            }
            $type = $property->getType();
            if (!$type) {
                throw new PropertiesMustBeTypeHintedException(sprintf('Found in %s::%s', get_class($this), $name));
            }
            $optional = $type->getName() === 'bool'
                ? GetOpt::NO_ARGUMENT
                : ($type->getName() === 'array'
                    ? GetOpt::MULTIPLE_ARGUMENT
                    : (isset($defaults[$property->getName()]) ? GetOpt::OPTIONAL_ARGUMENT : GetOpt::REQUIRED_ARGUMENT)
                );
            $option = new Option($short, $long, $optional);
            $this->_optionList[$long ?? $short] = $option;
        }
    }

    private function convertParametersToOperands() : Generator
    {
        if (!method_exists($this, '__invoke')) {
            return;
        }
        $invoker = new ReflectionMethod($this, '__invoke');
        foreach ($invoker->getParameters() as $parameter) {
            $name = $parameter->getName();
            $default = $parameter->isDefaultValueAvailable();
            $mode = $default ? Operand::OPTIONAL : Operand::REQUIRED;
            $type = $parameter->getType();
            if ($type->getName() === 'array') {
                $mode |= Operand::MULTIPLE;
            }
            $operand = new Operand($name, $mode);
            if ($default and $defaultValue = $parameter->getDefaultvalue()) {
                $operand->setDefaultValue($defaultValue);
            }
            yield $operand;
        }
    }

    private function convertOptionToProperty(string $name, mixed $value) : void
    {
        $name = self::toPropertyName($name);
        if (!property_exists($this, $name)) {
            return;
        }
        $type = (new ReflectionProperty($this, $name))->getType();
        if (!$type) {
            throw new PropertiesMustBeTypeHintedException(sprintf('Found in %s::%s', get_class($this), $name));
        }
        switch ($type->getName()) {
            case 'bool':
                $this->$name = !$this->$name;
                break;
            case 'array':
                $this->$name = $value;
                array_walk($this->$name, function (string &$value) : void {
                    if (!strlen($value)) {
                        $value = null;
                    }
                });
                break;
            case 'string':
                $this->$name = strlen($value) ? $value : null;
                break;
            default:
                throw new IllegalPropertyTypeHintException(sprintf('Found in %s:%s (found type %s)', get_class($this), $name, $type->getName()));
        }
    }

    /**
     * @param string $name
     * @return string
     */
    private static function toFlagName(string $name) : string
    {
        return preg_replace_callback('@([A-Z])@', function ($match) {
            return strtolower("-{$match[1]}");
        }, $name);
    }

    /**
     * @param string $name
     * @return string
     */
    private static function toPropertyName(string $name) : string
    {
        return preg_replace_callback('@-([a-z])@', function ($match) {
            return strtoupper($match[1]);
        }, $name);
    }
}

