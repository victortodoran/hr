<?php

use App\Enums\Scope;

covers(Scope::class);

it('picks everyone as the widest Scope', function () {
    expect(Scope::widest([Scope::Own, Scope::Everyone, Scope::Reports]))->toBe(Scope::Everyone);
});

it('picks reports over own', function () {
    expect(Scope::widest([Scope::Own, Scope::Reports]))->toBe(Scope::Reports);
});

it('keeps a single Scope', function () {
    expect(Scope::widest([Scope::Own]))->toBe(Scope::Own);
});

it('has no Scope when no Role grants the Permission', function () {
    expect(Scope::widest([]))->toBeNull();
});

it('picks the widest Scope whatever order the Roles come in', function (array $scopes, Scope $widest) {
    expect(Scope::widest($scopes))->toBe($widest);
})->with([
    'reports then everyone' => [[Scope::Reports, Scope::Everyone], Scope::Everyone],
    'everyone then reports' => [[Scope::Everyone, Scope::Reports], Scope::Everyone],
    'reports then own' => [[Scope::Reports, Scope::Own], Scope::Reports],
    'own then reports' => [[Scope::Own, Scope::Reports], Scope::Reports],
    'own then everyone' => [[Scope::Own, Scope::Everyone], Scope::Everyone],
]);

it('covers other Employees only when it is wider than own', function (Scope $scope, bool $expected) {
    expect($scope->coversOthers())->toBe($expected);
})->with([
    'own' => [Scope::Own, false],
    'reports' => [Scope::Reports, true],
    'everyone' => [Scope::Everyone, true],
]);
