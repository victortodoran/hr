<?php

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;

covers(AppServiceProvider::class);

it('refuses an attribute the model does not accept, instead of dropping it silently', function () {
    $evaluation = PerformanceEvaluation::factory()->completed()->create();

    expect(fn () => $evaluation->update(['state' => 'open']))->toThrow(MassAssignmentException::class)
        ->and($evaluation->refresh()->isCompleted())->toBeTrue();
});

it('refuses to load a relation one model at a time across a list, so a missing eager load fails in tests', function () {
    PerformanceEvaluation::factory()->count(2)->create();

    expect(fn () => PerformanceEvaluation::query()->get()->each(fn (PerformanceEvaluation $evaluation): Employee => $evaluation->employee))
        ->toThrow(LazyLoadingViolationException::class);
});
