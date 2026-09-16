<?php

namespace GrantHolle\Scru64;

use RuntimeException;

/**
 * Global generator configured from the SCRU64_NODE_SPEC environment variable.
 */
class Scru64
{
    private static ?Scru64Generator $generator = null;

    public static function generate(): Scru64Id
    {
        return self::generator()->generate();
    }

    public static function generator(): Scru64Generator
    {
        if (self::$generator === null) {
            $spec = getenv('SCRU64_NODE_SPEC');

            if ($spec === false || $spec === '') {
                throw new RuntimeException('SCRU64_NODE_SPEC environment variable is not set');
            }

            self::$generator = Scru64Generator::fromNodeSpec($spec);
        }

        return self::$generator;
    }

    public static function setGenerator(?Scru64Generator $generator): void
    {
        self::$generator = $generator;
    }
}
