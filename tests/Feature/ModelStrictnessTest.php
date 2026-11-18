<?php

use App\Models\PerformanceEvaluation;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\MassAssignmentException;

covers(AppServiceProvider::class);

it('refuses an attribute the model does not accept, instead of dropping it silently', function () {
    $evaluation = PerformanceEvaluation::factory()->completed()->create();

    expect(fn () => $evaluation->update(['state' => 'open']))->toThrow(MassAssignmentException::class)
        ->and($evaluation->refresh()->isCompleted())->toBeTrue();
});
