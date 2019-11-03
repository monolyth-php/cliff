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

> If the command class does not extend `Monolyth\Cliff\Command`, everything will
> run as normal - but obviously you won't be able to make use of Cliff's
> argument parsing, help features (see below) etc. Cliff will show a warning
> about it; if you're not using Cliff you shouldn't, ehm, be using Cliff ;)

## Flags
A "flag" is an invocation component prefixed by `-` or `--`. Traditionally on
Unix-like systems, a single dash implies "followed by a single letter, and
optionally a value", whilst the double dash implies "followed by multiple
letters, an equals sign and a more complex value". `cliff` follows this
tradition.

All flags are set as properties on the command. The flags are "normalised",
i.e. a flag `my_user_name` will end up as `$this->myUserName`.

The implementor should define all flags as public properties. Any non-public
property is not considered a flag but e.g. a dependency injection.

### Defining short-hand flags
The default for any flag is to also define a short-hand version with its first
letter in lowercase, e.g. a flag `--password` will also be available as `-p`.
For complicated commands with many arguments, this may give conflicts. `cliff`
will first use the uppercased version of the duplicate argument. If that too is
already taken, it will ignore the duplicate shorthand flag.

So e.g. if your command class specifies the `file`, `format` and `foo`
properties, only `-f` for `---file` and `-F` for format will be available as a
shorthand.

To explicitly specify the short-hand version you want, you may annotate the
property:

```php
<?php

//...
/** @Alias o */
public $foo;
```

Note that only the longhand variant is set as a property, unless the flag is
defined as shorthand-only. In other words, only the properties defined on the
class are used.

## Flag types
Flags come in three variants: required, optional and empty (an empty flag is
optional by default). Defining these in your command class is simple:

```php
<?php

class Command extends \Monolyth\Cliff\Command
{
    /** @var string */
    public $requiredFlag; // No default value, so this is required
    /** @var string */
    public $optionalFlag = 'foo'; // The default value means this is optional
    /** @var bool */
    public $emptyFlag = false; // Boolean flags are per defintion empty
}

```

Note that the marking the flag "required" simply means one _has_ to pass a value
when using the flag; the flag itself can never be "required". If you require
values to be passed to the command when run, use _arguments_.

In other words, an optional flag can act as either having a value or a boolean.

If an optional argument has a default value of an empty string, it is set to
`NULL` instead so various PHP coalesce operators will work as expected.

### Negating empty flags
If an empty flag is set, the default boolean value is _negated_. So if the
default is `true`, setting the flag makes it `false` and vice versa. Typically a
default of `false` will make the most sense, but this might come in handy if
your code's logic is... peculiar.

## Documentation, help and error reporting
Via reflection, the doccomments of the class, the `__invoke` method and the
options properties are utilised.

Use the doccomment of the class to describe general usage. This is akin to
calling common Unix commands with the `-h[elp]` flag.

Use the doccomment of your `__invoke` method to described which arguments the
command expects. This message is shown when any required argument is missing.

Use the doccomment of any option property for detailed help when it is passed as
a value to the `-h` or `--help` options. This option is defined on the `Command`
baseclass. You _can_ override it in your own command, but it is handled in the
base constructor (which you probably don't want to override...).

If the `__invoke` method throws any uncaught exception, its message is shown
and a non-zero exit status is returned. Note that exit status 1 is reserved for
when required arguments are missing.

## Preloading files
Like `vendor/autoload.php`, your command might require some bootstrapping, e.g.
if you're using a framework. You can use the `@preload [filename]` annotation to
automate this (instead of using `require_once` calls which are slightly ugly).
This annotation should be placed on the docblock of the class.

Multiple `@preload` annotations are included in order. Note that all paths
should be relative to `getcwd()`.

