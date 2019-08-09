# monolyth/cliff
Command line interface for Monolyth unframework

## Installation
Using Composer:

```sh
composer required monolyth/cliff
```

## Usage
`cliff` is Monolyth's command line tool. It allows you to create and run
commands from your CLI, supporting arguments.

```sh
vendor/bin/cliff namespace/of/command [arguments]
```

`cliff` normalises the command given by capitalising the namespace and replacing
forward with backward slashes. Thus, the command `my/command` will resolve to a
classname of `My\Command` which Composer's autoloader must be able to load.

If the command's classname is itself `Command`, `cliff` will prepend that
automagically. Thus, if no class `My\Command` is found, it will look for
`My\Command\Command` before giving up.

All `cliff` command classes _must_ extend `Monolyth\Cliff\Command` for
convenience reasons (see below).

## Writing commands
Write a class extending `Monolyth\Cliff\Command`. It is easiest to call the
class itself `Command` too so you may omit it on the CLI, but this is not
required in any way:

```php
<?php

namespace My\Module;

use Monolyth\Cliff;

class Command extends Cliff\Command
{
}

```

Whenever a `cliff` command is called, its magic `__invoke` method is called. Any
_arguments_ passed to the invocation will be passed verbatim, in order. So, say
your command expects `path` and `filename` as arguments, your definition would
be as follows:

```php
<?php

//...
public function __invoke(string $path, string $filename)
{
    // ...do your thang...
}

```

If the command gets called with any required argument missing, `cliff` will
automatically show a helpful error message (see below).

## Flags
A "flag" is an invocation component prefixed by `-` or `--`. Traditionally on
Unix-like systems, a single dash implies "followed by a single letter, a space
and optionally a value", whilst the double dash implies "followed by multiple
letters, an equals sign and a more complex value". `cliff` follows this
tradition.

All flags are set on the command using magic setters. The flags are
"normalised", i.e. a flag `my_user_name` will end up as `$this->myUserName`.

The implementor should define all flags as properties (their visibility doesn't
really matter, but if certain commands should support base class extension
beware of private/protected issues. _Any_ property on the command class is
considered a flag (with its reverse-normalised name).

### Defining short-hand flags
The default for any flag is to also define a short-hand version with its first
letter in uppercase, e.g. a flag `--password` will also be available as `-P`.
For complicated commands with many arguments, this may give conflicts. `cliff`
will throw an error in that case (`FlagNotResolvableExeception`). To specify the
short-hand version you want, you may annotate the property:

```php
<?php

//...
/** @Alias W */
public $password;
```

## Error reporting
Via reflection, the doccomments of both the class and the `__invoke` method are
utilised.

Use the doccomment of the class to describe general usage. This is akin to
calling common Unix commands with the `-h[elp]` flag. This message is shown
whenever the command is called with any required argument is missing.

The doccomment of the `__invoke` method is shown whenever it returns a non-zero
value.

If the `__invoke` method throws any uncaught exception, its message instead is
shown (with helpful details if you're using `Monolyth\Envy`).

