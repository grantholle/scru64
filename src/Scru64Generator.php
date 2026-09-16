<?php

namespace GrantHolle\Scru64;

use InvalidArgumentException;
use RuntimeException;

class Scru64Generator
{
    /** Clock rollback tolerated before refusing to generate, in 256ms ticks (~10s). */
    private const int ROLLBACK_ALLOWANCE = 40;

    private int $counterSize;

    private int $prevTimestamp = 0;

    private int $prevNodeCtr;

    public function __construct(int $nodeId, int $nodeIdSize)
    {
        if ($nodeIdSize < 1 || $nodeIdSize > 23) {
            throw new InvalidArgumentException("node_id_size must be 1-23, got {$nodeIdSize}");
        }

        if ($nodeId < 0 || $nodeId >= 1 << $nodeIdSize) {
            throw new InvalidArgumentException("node_id out of range for {$nodeIdSize}-bit size: {$nodeId}");
        }

        $this->counterSize = 24 - $nodeIdSize;
        $this->prevNodeCtr = $nodeId << $this->counterSize;
    }

    /**
     * Parse a node spec: "42/8", "0xb00/12", or "0u2r85hm2pt3/16" (continue after a previous ID).
     */
    public static function fromNodeSpec(string $spec): self
    {
        if (! preg_match('/^([0-9a-zA-Z]+)\/(\d+)$/', $spec, $m)) {
            throw new InvalidArgumentException("invalid node spec: {$spec}");
        }

        $nodeIdSize = (int) $m[2];

        if (strlen($m[1]) === 12) {
            $prev = Scru64Id::fromString($m[1]);
            $generator = new self($prev->nodeCtr() >> (24 - $nodeIdSize), $nodeIdSize);
            $generator->prevTimestamp = $prev->timestamp();
            $generator->prevNodeCtr = $prev->nodeCtr();

            return $generator;
        }

        if (preg_match('/^0x([0-9a-f]+)$/i', $m[1], $hex)) {
            return new self((int) hexdec($hex[1]), $nodeIdSize);
        }

        if (! ctype_digit($m[1])) {
            throw new InvalidArgumentException("invalid node spec: {$spec}");
        }

        return new self((int) $m[1], $nodeIdSize);
    }

    /**
     * @throws RuntimeException on a clock rollback larger than ~10 seconds
     */
    public function generate(): Scru64Id
    {
        return $this->generateAt(intdiv((int) floor(microtime(true) * 1000), 256));
    }

    /** @internal exposed for tests; $timestamp is a 256ms tick */
    public function generateAt(int $timestamp): Scru64Id
    {
        $counterMask = (1 << $this->counterSize) - 1;

        if ($timestamp > $this->prevTimestamp) {
            $this->prevTimestamp = $timestamp;
            $this->resetCounter($counterMask);
        } elseif ($timestamp + self::ROLLBACK_ALLOWANCE > $this->prevTimestamp) {
            // small rollback (or counter-overflow drift): keep prev timestamp, advance counter
            $counter = ($this->prevNodeCtr & $counterMask) + 1;

            if ($counter > $counterMask) {
                $this->prevTimestamp++;
                $this->resetCounter($counterMask);
            } else {
                $this->prevNodeCtr++;
            }
        } else {
            throw new RuntimeException('SCRU64 clock rollback too large to guarantee monotonic IDs');
        }

        return Scru64Id::fromParts($this->prevTimestamp, $this->prevNodeCtr);
    }

    private function resetCounter(int $counterMask): void
    {
        // leading counter bit reserved as overflow guard, per spec recommendation
        $this->prevNodeCtr = ($this->prevNodeCtr & ~$counterMask) | random_int(0, $counterMask >> 1);
    }
}
