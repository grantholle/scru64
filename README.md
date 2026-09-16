# SCRU64 for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/grantholle/scru64.svg?style=flat-square)](https://packagist.org/packages/grantholle/scru64)
[![Tests](https://github.com/grantholle/scru64/actions/workflows/run-tests.yml/badge.svg)](https://github.com/grantholle/scru64/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/grantholle/scru64.svg?style=flat-square)](https://packagist.org/packages/grantholle/scru64)

A PHP implementation of [SCRU64](https://github.com/scru64/spec): Sortable, Clock-based, Realm-specifically Unique identifiers.

- 63-bit non-negative integer — fits in a signed `BIGINT`
- Sortable by generation time, both as integer and as text
- 12-digit case-insensitive Base36 text (`0u375nxqh5cq`)
- 256 ms timestamp resolution, usable until year 4261
- Node ID and counter share 24 bits; size is configurable per node

Uniqueness depends on each generator being given a unique node ID. If you can't coordinate node IDs, use [SCRU128](https://github.com/scru128/spec) instead.

## Installation

```bash
composer require grantholle/scru64
```

Requires PHP 8.4+ on a 64-bit platform.

## Usage

### Global generator

Set `SCRU64_NODE_SPEC` in the environment and call the static facade:

```bash
SCRU64_NODE_SPEC=42/8 php app.php
```

```php
use GrantHolle\Scru64\Scru64;

$id = Scru64::generate();

echo $id;          // "0w2sp3sj5t2i"
echo $id->value;   // 117281148432446826
```

The environment variable is only needed by the facade. If you'd rather configure it in code (e.g. from your app's config during bootstrap), set the generator explicitly and the env var is ignored:

```php
use GrantHolle\Scru64\Scru64;
use GrantHolle\Scru64\Scru64Generator;

Scru64::setGenerator(Scru64Generator::fromNodeSpec('42/8'));

Scru64::generate();
```

`Scru64::generate()` throws a `RuntimeException` if neither is provided.

### Node spec

A node spec is `<node_id>/<node_id_size>`, where `node_id_size` is 1–23 bits:

| Spec              | Meaning                                                      |
| ----------------- | ------------------------------------------------------------ |
| `42/8`            | Decimal node ID 42 using 8 bits (16-bit counter)             |
| `0xb00/12`        | Hex node ID using 12 bits (12-bit counter)                   |
| `0u2r85hm2pt3/16` | Continue after a previously generated ID, using 16 node bits |

Fewer node bits → more IDs per 256 ms tick per node. More node bits → more nodes.

### Explicit generator

```php
use GrantHolle\Scru64\Scru64Generator;

$generator = new Scru64Generator(nodeId: 42, nodeIdSize: 8);
// or
$generator = Scru64Generator::fromNodeSpec('42/8');

$id = $generator->generate();
```

`generate()` never blocks. When the counter overflows within a tick, the timestamp is advanced by one. Clock rollbacks of up to ~10 seconds are absorbed; a larger rollback throws a `RuntimeException`.

### Working with IDs

```php
use GrantHolle\Scru64\Scru64Id;

$id = Scru64Id::fromString('0u2pf62ji4b9');  // case-insensitive
$id = new Scru64Id(109959589539758421);
$id = Scru64Id::fromParts(timestamp: 6554000, nodeCtr: 0x2a0000);

(string) $id;      // "0u2pf62ji4b9"
$id->value;        // 109959589539758421
$id->timestamp();  // Unix time in 256 ms units
$id->nodeCtr();    // combined 24-bit node ID + counter field
```

Store `$id->value` in a `BIGINT` column or `(string) $id` in a `CHAR(12)` column; both sort chronologically.

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Grant Holle](https://github.com/grantholle)
- [SCRU64 specification](https://github.com/scru64/spec) by LiosK
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
