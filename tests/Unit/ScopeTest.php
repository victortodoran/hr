<?php

use App\Enums\Scope;

covers(Scope::class);

it('covers other Employees only when it is wider than own', function (Scope $scope, bool $expected) {
    expect($scope->coversOthers())->toBe($expected);
})->with([
    'own' => [Scope::Own, false],
    'reports' => [Scope::Reports, true],
    'everyone' => [Scope::Everyone, true],
]);
