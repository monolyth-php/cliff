<?php

namespace Monolyth\Cliff\Test;

use Monolyth\Cliff\Command;
use Generator;
use GetOpt\GetOpt;
use PHPUnit\Framework\TestCase;

final class CommandTest extends TestCase
{
    public function testWeCanInstantiateACommandWithDefaultCLIOptions() : void
    {
        $command = new class(['bar']) extends Command {

            public bool $bar = false;

            private string $foo = '';

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

                public bool $bar = false;

                private string $foo = '';

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

