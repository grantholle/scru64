<?php

namespace GrantHolle\Scru64;

use InvalidArgumentException;
use Stringable;

/**
 * A SCRU64 ID: a 63-bit integer laid out as `timestamp << 24 | node_id << (24 - node_id_size) | counter`.
 */
readonly class Scru64Id implements Stringable
{
    /** 36^12 - 1 */
    public const int MAX = 4738381338321616895;

    /** 36^12 / 2^24 - 1 */
    public const int MAX_TIMESTAMP = 282429536480;

    private const string DIGITS = '0123456789abcdefghijklmnopqrstuvwxyz';

    public function __construct(public int $value)
    {
        if ($value < 0 || $value > self::MAX) {
            throw new InvalidArgumentException("SCRU64 ID out of range: {$value}");
        }
    }

    public static function fromParts(int $timestamp, int $nodeCtr): self
    {
        if ($timestamp < 0 || $timestamp > self::MAX_TIMESTAMP) {
            throw new InvalidArgumentException("timestamp out of range: {$timestamp}");
        }

        if ($nodeCtr < 0 || $nodeCtr > 0xFFFFFF) {
            throw new InvalidArgumentException("node_ctr out of range: {$nodeCtr}");
        }

        return new self($timestamp << 24 | $nodeCtr);
    }

    public static function fromString(string $text): self
    {
        $text = strtolower($text);

        if (! preg_match('/^[0-9a-z]{12}$/', $text)) {
            throw new InvalidArgumentException("invalid SCRU64 string: {$text}");
        }

        // ponytail: manual base36 loop; base_convert() loses precision above 2^53
        $value = 0;

        foreach (str_split($text) as $char) {
            $value = $value * 36 + strpos(self::DIGITS, $char);

            if ($value > self::MAX) {
                throw new InvalidArgumentException("SCRU64 string out of range: {$text}");
            }
        }

        return new self($value);
    }

    /** 256-millisecond-precision Unix timestamp. */
    public function timestamp(): int
    {
        return $this->value >> 24;
    }

    /** Combined 24-bit node_id and counter field. */
    public function nodeCtr(): int
    {
        return $this->value & 0xFFFFFF;
    }

    /** 12-digit lowercase base36 representation. */
    public function __toString(): string
    {
        $num = $this->value;
        $text = '';

        for ($i = 0; $i < 12; $i++) {
            $text = self::DIGITS[$num % 36].$text;
            $num = intdiv($num, 36);
        }

        return $text;
    }
}
