<?php

use GetOpt\GetOpt;

/** Testsuite for Cliff commands */
return function () : Generator {
    /** We can instantiate a command with default (CLI) options. */
    yield function () : void {
        $command = new class([]) extends Monolyth\Cliff\Command {

            private $foo = '';

            public $bar = false;

            public function __invoke(string $arg)
            {
                $this->foo = $arg;
            }

            public function getFoo() : string
            {
                return $this->foo;
            }
        };

        $command('bar');
        assert($command->getFoo() === 'bar');
        assert($command->bar === false);
    };

    /** We can instantiate a command with custom options. */
    yield function () : void {
        foreach (['--bar', '-b'] as $argument) {
            $command = new class([$argument]) extends Monolyth\Cliff\Command {

                private $foo = '';

                public $bar = false;

                public function __invoke(string $arg)
                {
                    $this->foo = $arg;
                }

                public function getFoo() : string
                {
                    return $this->foo;
                }
            };

            $command('bar');
            assert($command->getFoo() === 'bar');
            assert($command->bar === true);
        }
    };

    /** toPhpName correctly converts command names to PHP ones. */
    yield function () : void {
        $name = Monolyth\Cliff\Command::toPhpName('foo:bar');
        assert($name === 'Foo\Bar');
        $name = Monolyth\Cliff\Command::toPhpName('foo/bar');
        assert($name === 'Foo\Bar');
    };
};

