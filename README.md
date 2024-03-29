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

To explicitly specify the short-hand version you want, you may use an attribute:

```php
<?php

//...
#[\Monolyth\Cliff\Alias("o")]
public $foo;
```

Note that only the longhand variant is set as a property, unless the option is
defined as shorthand-only (i.e., a property with a single-letter name). In other
words, only the properties defined on the class are used.

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
    public string $requiredOption; // No default value, so this is required

    public string $optionalOption = 'foo'; // The default value means this is optional

    public bool $emptyOption = false; // Boolean options are per defintion empty
}

```

Note that the marking the option "required" simply means one _has_ to pass a
value when using the option; the option itself can never be "required". If you
always require values to be passed to the command when run, use _arguments_ to
your `__invoke` method.

In other words, an optional option can act as either having a value or a
boolean.

If an optional argument has a default value of an empty string, it is set to
`NULL` instead so various PHP coalesce operators will work as expected. Hence,
optional string options with no default _must_ be nullable (`?string`).

Only the `string`, `bool` and `array` types are allowed for option properties.
This is the nature of a CLI interface.

### Negating empty options
If an empty option is set, the default boolean value is _negated_. So if the
default is `true`, setting the option makes it `false` and vice versa. Typically
a default of `false` will make the most sense, but this might come in handy if
your code's logic is... peculiar.

### Array options
If an option is type hinted as an array (of strings), it automatically allows
multiple values to be paased, e.g.:

```sh
$ vendor/bin/cliff my/command --test=foo --test=bar
```

Like with string options, empty strings will be forced to `null`.

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

## Chaining commands
Commands may be chained or output redirected using the standard features of your
operating system of choice. E.g., on Unix-like systems this would be done using
the pipe (`|`) and angular bracket (`<`) operator. Prior to Cliff 0.7, a more
convoluted mechanism was used, but why reinvent the wheel, right?

Input may be _read_ using PHP's standard `STDIN` stream. To output something
another script or command can use as input, simply `echo` it.

A previous version of this readme had the following example: a command that
generated a list of users, and another command that filtered it by female users
only. This could now be achieved with something like this:

```sh
vendor/bin/cliff users | vendor/bin/cliff females > females.csv
```

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
if you're using a framework. You can use the `Monolyth\Cliff\Preload("filename")`
attribute to automate this (instead of using `require_once` calls which are
slightly ugly). This attribute should be placed on the class.

Multiple `Preload` attributes are included in order. Note that all paths should
be relative to `getcwd()`. If you prefer `require_once` though, be our guest.
:-)

