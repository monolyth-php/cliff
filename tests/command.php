<?php

namespace Monolyth\Cliff\Test;

use Monolyth\Cliff\Command;
use Generator;

class FooCommand extends Command
{
    public function __invoke(string $forwardedCommand) : void
    {
    }
}

class BarCommand extends Command
{
    /** @var bool */
    public $foo = false;

    /** @var string */
    public $bar;

    /** @var bool */
    private static $forwarded = false;

    public function __invoke() : void
    {
        self::$forwarded = true;
    }

    public static function wasForwarded() : bool
    {
        return self::$forwarded;
    }
}

use GetOpt\GetOpt;

/** Testsuite for Cliff commands */
return function () : Generator {
    /** We can instantiate a command with default (CLI) options. */
    yield function () : void {
        $command = new class(['bar']) extends Command {

            /** @var bool */
            public $bar = false;

            /** @var string */
            private $foo = '';

            public function __invoke(string $arg)
            {
                $this->foo = $arg;
            }

            public function getFoo() : string
            {
                return $this->foo;
            }
        };

        $command->execute();
        assert($command->getFoo() === 'bar');
        assert($command->bar === false);
    };

    /** We can instantiate a command with custom options. */
    yield function () : void {
        foreach (['--bar', '-b'] as $argument) {
            $command = new class(['bar', $argument]) extends Command {

                /** @var bool */
                public $bar = false;

                /** @var string */
                private $foo = '';

                public function __invoke(string $arg)
                {
                    $this->foo = $arg;
                }

                public function getFoo() : string
                {
                    return $this->foo;
                }
            };

            $command->execute();
            assert($command->getFoo() === 'bar');
            assert($command->bar === true);
        }
    };

    /** toPhpName correctly converts command names to PHP ones. */
    yield function () : void {
        $name = Command::toPhpName('foo:bar');
        assert($name === 'Foo\Bar');
        $name = Command::toPhpName('foo/bar');
        assert($name === 'Foo\Bar');
    };

    /** A command can forward to other commands, with the correct operands passed. */
    yield function () : void {
        $command = new FooCommand(['monolyth/cliff/test/bar-command', '--foo', '--bar=baz']);
        $command->execute();
        assert(BarCommand::wasForwarded() === true);
    };
};

