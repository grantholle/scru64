<?php

use GrantHolle\Scru64\Scru64;
use GrantHolle\Scru64\Scru64Generator;
use GrantHolle\Scru64\Scru64Id;

it('generates monotonic ids with the right node id', function () {
    $gen = new Scru64Generator(42, 8);
    $prev = $gen->generate();

    for ($i = 0; $i < 10_000; $i++) {
        $id = $gen->generate();
        expect($id->value)->toBeGreaterThan($prev->value)
            ->and($id->nodeCtr() >> 16)->toBe(42);
        $prev = $id;
    }
});

it('advances timestamp on counter overflow', function () {
    $gen = new Scru64Generator(0, 23); // 1-bit counter
    $ts = 1_000_000;
    $first = $gen->generateAt($ts); // counter reset to 0 (guard bit)
    $second = $gen->generateAt($ts); // counter 1
    $third = $gen->generateAt($ts); // overflow

    expect($first->timestamp())->toBe($ts)
        ->and($second->timestamp())->toBe($ts)
        ->and($third->timestamp())->toBe($ts + 1)
        ->and($second->value)->toBeGreaterThan($first->value)
        ->and($third->value)->toBeGreaterThan($second->value);
});

it('tolerates small rollbacks but rejects large ones', function () {
    $gen = new Scru64Generator(1, 8);
    $ts = 1_000_000;
    $a = $gen->generateAt($ts);
    $b = $gen->generateAt($ts - 39);

    expect($b->timestamp())->toBe($ts)
        ->and($b->value)->toBeGreaterThan($a->value)
        ->and(fn () => $gen->generateAt($ts - 40))->toThrow(RuntimeException::class);
});

it('parses node specs', function (string $spec, int $nodeId, int $size) {
    $id = Scru64Generator::fromNodeSpec($spec)->generate();
    expect($id->nodeCtr() >> (24 - $size))->toBe($nodeId);
})->with([
    ['42/8', 42, 8],
    ['0xb00/12', 0xB00, 12],
    ['0/1', 0, 1],
    ['8388607/23', 8388607, 23],
]);

it('continues after a previous id', function () {
    $prev = Scru64Id::fromString('0u2r85hm2pt3');
    $id = Scru64Generator::fromNodeSpec('0u2r85hm2pt3/16')->generate();

    expect($id->value)->toBeGreaterThan($prev->value)
        ->and($id->nodeCtr() >> 8)->toBe($prev->nodeCtr() >> 8);
});

it('rejects bad node specs', function (Closure $fn) {
    expect($fn)->toThrow(InvalidArgumentException::class);
})->with([
    fn () => Scru64Generator::fromNodeSpec('42'),
    fn () => Scru64Generator::fromNodeSpec('42/0'),
    fn () => Scru64Generator::fromNodeSpec('42/24'),
    fn () => Scru64Generator::fromNodeSpec('256/8'),
    fn () => Scru64Generator::fromNodeSpec('0xzz/8'),
    fn () => new Scru64Generator(-1, 8),
]);

it('uses the global generator from the environment', function () {
    Scru64::setGenerator(null);
    putenv('SCRU64_NODE_SPEC');
    expect(fn () => Scru64::generate())->toThrow(RuntimeException::class);

    putenv('SCRU64_NODE_SPEC=7/4');
    expect(Scru64::generate()->nodeCtr() >> 20)->toBe(7);
    putenv('SCRU64_NODE_SPEC');
    Scru64::setGenerator(null);
});
