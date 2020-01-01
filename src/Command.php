<?php

namespace Monolyth\Cliff;

use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\ArgumentException\Unexpected;
use GetOpt\Operand;
use Throwable;
use Monomelodies\Reflex\ReflectionObject;
use Monomelodies\Reflex\ReflectionMethod;
use Monomelodies\Reflex\ReflectionProperty;
use zpt\anno\Annotations;
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
    public $help = '*';

    /** var Monolyth\Cliff\Command */
    protected $_forwardedFrom;

    /** var Monolyth\Cliff\Command */
    protected $_forwardedCommand;

    /** @var array */
    private $_optionList = [];

    /** @var GetOpt\GetOpt */
    private $_getopt;

    /**
     * @param array|null $arguments Optional manual arguments.
     * @param Monolyth\Cliff\Command|null Optional forwarding command.
     * @return void
     */
    public function __construct(array $arguments = null, Command $forwardingCommand = null)
    {
        $this->_optionList = [];
        $this->_forwardedFrom = $forwardingCommand;
        $this->process($arguments);
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
        if (isset($this->_forwardedCommand)) {
            $this->_forwardedCommand->execute();
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
        $parts = preg_split('@[:/]@', self::toPropertyName($name));
        array_walk($parts, function (&$part) : void {
            $part = ucfirst($part);
        });
        return implode('\\', $parts);
    }

    /**
     * @param array|null $arguments Optional manual arguments.
     * @return void
     */
    private function process(array $arguments = null) : void
    {
        $getopt = new GetOpt(null, [GetOpt::SETTING_STRICT_OPTIONS => false]);
        $reflection = new ReflectionObject($this);
        $annotations = new Annotations($reflection);
        if (isset($annotations['preload'])) {
            $this->preloadDependencies(...(is_array($annotations['preload']) ? $annotations['preload'] : [$annotations['preload']]));
        }
        $this->convertPropertiesToOptions($reflection);
        $getopt->addOptions($this->_optionList);
        foreach ($this->convertParametersToOperands($getopt) as $operand) {
            $getopt->addOperand($operand);
        }
        $getopt->process($arguments);
        $operands = $getopt->getOperands();
        if ($operands) {
            $test = array_shift($operands);
            $test = self::toPhpName($test);
            if (!class_exists($test)) {
                $test = $test.'\Command';
            }
            if (class_exists($test) && is_subclass_of($test, __CLASS__)) {
                if (is_null($arguments)) {
                    $arguments = array_splice($_SERVER['argv'], 2);
                } else {
                    array_shift($arguments);
                }
                $this->_forwardedCommand = new $test($arguments, $this);
                $this->_getopt = $getopt;
                return;
            }
        }
        $getopt = new GetOpt(null, [GetOpt::SETTING_STRICT_OPTIONS => true]);
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
     * @param string ...$dependencies
     * @return void
     */
    private function preloadDependencies(string ...$dependencies) : void
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
                : ($type === 'array'
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
            if ("$type" === 'array') {
                $mode |= Operand::MULTIPLE;
            }
            $operand = new Operand($name, $mode);
            if ($default and $defaultValue = $parameter->getDefaultvalue()) {
                $operand->setDefaultValue($defaultValue);
            }
            yield $operand;
        }
    }

    private function convertOptionToProperty(string $name, $value) : void
    {
        $name = self::toPropertyName($name);
        if (!property_exists($this, $name)) {
            return;
        }
        $type = gettype($this->$name);
        if ($type == 'boolean') {
            $this->$name = !$this->$name;
        } else {
            if (gettype($value) === 'string' || gettype($value) === 'array') {
                $this->$name = $value;
            } elseif ($type === 'string' && !strlen($this->$name)) {
                $this->$name = null;
            }
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

