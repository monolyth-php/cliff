<?php

namespace Monolyth\Cliff\Test;

use Monolyth\Cliff\Command;
use Generator;

class FooCommand extends Command
{
    public function __invoke(string $forwardedCommand) : void
    {
        echo 1;
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

    public function __invoke(string $forwardedCommand = null) : void
    {
        echo 2;
        self::$forwarded = true;
    }

    public static function wasForwarded() : bool
    {
        return self::$forwarded;
    }
}

class BuzzCommand extends Command
{
    public function __invoke() : void
    {
        echo 3;
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
        ob_start();
        $command->execute();
        ob_end_clean();
        assert(BarCommand::wasForwarded() === true);
    };

    /** Forwarding can happen to an arbitrary level, with commands invoked in order. */
    yield function () : void {
        $command = new FooCommand(['monolyth/cliff/test/bar-command', 'monolyth/cliff/test/buzz-command']);
        ob_start();
        $command->execute();
        $result = ob_get_clean();
        assert(BarCommand::wasForwarded() === true);
        assert($result === '123');
    };
};

