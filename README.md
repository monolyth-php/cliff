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

## Options
A "option" is an invocation component prefixed by `-` or `--`. Traditionally on
Unix-like systems, a single dash implies "followed by a single letter, and
optionally a value", whilst the double dash implies "followed by multiple
letters, an equals sign and a more complex value". `cliff` follows this
tradition.

All options are set as properties on the command. The options are "normalised",
i.e. an option `my-user-name` will end up as `$this->myUserName`.

The implementor should define all options as public properties. Any non-public
property is not considered a option but e.g. a dependency injection.

### Defining short-hand options
The default for any option is to also define a short-hand version with its first
letter in lowercase, e.g. a option `--password` will also be available as `-p`.
For complicated commands with many arguments, this may give conflicts. `cliff`
will first use the uppercased version of the duplicate argument. If that too is
already taken, it will ignore the duplicate shorthand option.

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

Note that only the longhand variant is set as a property, unless the option is
defined as shorthand-only. In other words, only the properties defined on the
class are used.

## Long option names
`$snakeCased` option properties are translated hyphen-separated options on the
CLI (`--snake-cased`) and vice versa. For Pete's sake don't use an upper case
character as the first one on your public properties; for one, it's butt-ugly;
but more importantly, Cliff won't account for this, so `$Foo` would become
`---foo` which makes no sense (and most probably will cause an error).

## Option types
Options come in three variants: required, optional and empty (an empty option is
optional by default). Defining these in your command class is simple:

```php
<?php

class Command extends \Monolyth\Cliff\Command
{
    /** @var string */
    public $requiredOption; // No default value, so this is required
    /** @var string */
    public $optionalOption = 'foo'; // The default value means this is optional
    /** @var bool */
    public $emptyOption = false; // Boolean options are per defintion empty
}

```

Note that the marking the option "required" simply means one _has_ to pass a
value when using the option; the option itself can never be "required". If you
always require values to be passed to the command when run, use _arguments_ to
your `__invoke` method.

In other words, an optional option can act as either having a value or a
boolean.

If an optional argument has a default value of an empty string, it is set to
`NULL` instead so various PHP coalesce operators will work as expected.

### Negating empty options
If an empty option is set, the default boolean value is _negated_. So if the
default is `true`, setting the option makes it `false` and vice versa. Typically
a default of `false` will make the most sense, but this might come in handy if
your code's logic is... peculiar.

## Manually passing options
Sometimes you'll want to run a command from another script, e.g. when using the
`Monolyth\Croney` scheduler. To override the options passed to the command, pass
an array containing the desired commands to the constructor:

```php
<?php

$command = new Foo\Command(['--bar', 'baz']);
$command->execute();

```

This is identical to invoking with `vendor/bin/cliff foo --bar baz`.

## Forwarding commands
As of version 0.6 Cliff supports a powerful mechanism to _forward_ commands. As
soon as the first passed operand resolves to a valid Cliff commandname,
execution is delegated verbatim to this command (minus the operand in question).

The `__invoke` method of the forwarding command is _not_ called. Instead, it is
available on the protected `_forwardedFrom` property. It is then up to the
implementor whether or not the forwarding command should be invoked, or if she
needs it for other reasons. Additionally, only the last non-forwarding command
has its options checked in strict mode.

Command forwarding can be extremely useful if commands need to exist in their
own right, but there are also (sub)commands that depend on them in any way. For
example, imagine you have a command to generate a CSV of users for seeding. A
subcommand could then take that list, filter it for e.g. only females, and
overwrite it. The subcommand would then only have to worry about the filtering,
not the generation.

This could of course also be achieved in a more programmatic way using extending
classes etc., but the advantage of subcommands is that the filter command from
the example does _not_ rely on the generation command; instead, it could also be
applied to a userlist from a different source (e.g. a database dump from the
production site).

In this way you can "chain" as many commands as you like. Name resolution
follows the exact same rules as normal Cliff commands.

## Documentation, help and error reporting
Via reflection, the doccomments of the class, the `__invoke` method and the
options properties are utilised.

Use the doccomment of the class to describe general usage. This is akin to
calling common Unix commands with the `-h[elp]` option.

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

