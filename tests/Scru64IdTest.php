<?php

use GrantHolle\Scru64\Scru64Id;

it('encodes and decodes the spec example', function () {
    $id = new Scru64Id(109959589539758421);

    expect((string) $id)->toBe('0u2pf62ji4b9')
        ->and(Scru64Id::fromString('0U2PF62JI4B9')->value)->toBe($id->value);
});

it('handles the bounds', function () {
    expect((string) new Scru64Id(0))->toBe('000000000000')
        ->and((string) new Scru64Id(Scru64Id::MAX))->toBe('zzzzzzzzzzzz')
        ->and(Scru64Id::fromString('zzzzzzzzzzzz')->value)->toBe(Scru64Id::MAX);
});

it('round trips random values and splits parts', function () {
    for ($i = 0; $i < 100; $i++) {
        $ts = random_int(0, Scru64Id::MAX_TIMESTAMP);
        $nodeCtr = random_int(0, 0xFFFFFF);
        $id = Scru64Id::fromParts($ts, $nodeCtr);

        expect($id->timestamp())->toBe($ts)
            ->and($id->nodeCtr())->toBe($nodeCtr)
            ->and(Scru64Id::fromString((string) $id)->value)->toBe($id->value);
    }
});

it('rejects invalid input', function (Closure $fn) {
    expect($fn)->toThrow(InvalidArgumentException::class);
})->with([
    fn () => new Scru64Id(-1),
    fn () => new Scru64Id(Scru64Id::MAX + 1),
    fn () => Scru64Id::fromString('0u2pf62ji4b'),
    fn () => Scru64Id::fromString('0u2pf62ji4b9x'),
    fn () => Scru64Id::fromString('0u2pf62ji4b-'),
    fn () => Scru64Id::fromString('zzzzzzzzzzzzz'),
    fn () => Scru64Id::fromParts(Scru64Id::MAX_TIMESTAMP + 1, 0),
    fn () => Scru64Id::fromParts(0, 0x1000000),
]);
