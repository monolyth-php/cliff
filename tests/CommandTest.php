<?php

namespace Monolyth\Cliff\Test;

use Monolyth\Cliff\Command;
use Generator;
use GetOpt\GetOpt;
use PHPUnit\Framework\TestCase;

class FooCommand extends Command
{
    public function __invoke(string $forwardedCommand) : void
    {
        echo 1;
    }
}

class BarCommand extends Command
{
    public bool $foo = false;

    public string $bar;

    private static bool $forwarded = false;

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

final class CommandTest extends TestCase
{
    public function testWeCanInstantiateACommandWithDefaultCLIOptions() : void
    {
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
        $this->assertEquals($command->getFoo(), 'bar');
        $this->assertEquals($command->bar, false);
    }

    public function testWeCanInstantiateACommandWithCustomOptions() : void
    {
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
            $this->assertEquals($command->getFoo(), 'bar');
            $this->assertEquals($command->bar, true);
        }
    }

    public function testToPhpNameCorrectlyConvertsCommandNamesToPHPOnes() : void
    {
        $name = Command::toPhpName('foo:bar');
        $this->assertEquals($name, 'Foo\Bar');
        $name = Command::toPhpName('foo/bar');
        $this->assertEquals($name, 'Foo\Bar');
    }
}

