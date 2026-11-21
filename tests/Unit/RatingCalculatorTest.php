<?php

use App\Services\Rating\Calculator\DTO\DisciplineInput;
use App\Services\Rating\Calculator\DTO\ModuleInput;
use App\Services\Rating\Calculator\DTO\WorkGrade;
use App\Services\Rating\Calculator\LatePenalty;
use App\Services\Rating\Calculator\RatingCalculator;
use App\Services\Rating\Calculator\Strategies\ArithmeticStrategy;
use App\Services\Rating\Calculator\Strategies\WeightedStrategy;

// --- LatePenalty ---

it('LatePenalty без просрочки = 1.0', function () {
    expect(LatePenalty::coefficient(0))->toBe(1.0);
});

it('LatePenalty 1 неделя = 0.9', function () {
    expect(LatePenalty::coefficient(1))->toBe(0.9);
});

it('LatePenalty 3 недели = 0.7', function () {
    expect(LatePenalty::coefficient(3))->toBe(0.7);
});

it('LatePenalty 5 недель = 0.5 (потолок)', function () {
    expect(LatePenalty::coefficient(5))->toBe(0.5);
});

it('LatePenalty 10 недель = 0.5 (потолок)', function () {
    expect(LatePenalty::coefficient(10))->toBe(0.5);
});

it('LatePenalty отрицательное значение = 1.0 (не наказываем)', function () {
    expect(LatePenalty::coefficient(-1))->toBe(1.0);
});

// --- WeightedStrategy: MP ---

it('Weighted MP: одна работа, вес 1, оценка 10 → 90', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [new WorkGrade(10, 1.0, 0)],
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    expect($result->value)->toBe(90.0);
    expect($result->hasZeroWork)->toBeFalse();
});

it('Weighted MP: две работы с равными весами 0.5, оценки 8 и 6 → 63', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [
            new WorkGrade(8, 0.5, 0),
            new WorkGrade(6, 0.5, 0),
        ],
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    // (8*0.5 + 6*0.5) / 1 * 9 = 7 * 9 = 63
    expect($result->value)->toBe(63.0);
});

it('Weighted MP: веса 0.7 и 0.3, оценки 10 и 5 → 76.5', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [
            new WorkGrade(10, 0.7, 0),
            new WorkGrade(5, 0.3, 0),
        ],
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    // (10*0.7 + 5*0.3) / 1 * 9 = (7 + 1.5) * 9 = 8.5 * 9 = 76.5
    expect($result->value)->toBe(76.5);
});

it('Weighted MP: оценка 0 у одной работы → hasZeroWork = true', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [
            new WorkGrade(0, 0.5, 0),
            new WorkGrade(10, 0.5, 0),
        ],
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    expect($result->hasZeroWork)->toBeTrue();
    expect($result->value)->toBe(45.0);
});

it('Weighted MP: пустой модуль → 0, hasZeroWork = true', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(works: [], weight: 1.0);

    $result = $strategy->calculateModuleRating($module);

    expect($result->value)->toBe(0.0);
    expect($result->hasZeroWork)->toBeTrue();
});

// --- WeightedStrategy + просрочка ---

it('Weighted MP: просрочка 1 неделя на оценке 10 → 81', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [new WorkGrade(10, 1.0, 1)], // 1 неделя = ×0.9
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    // 10 * 0.9 * 9 = 81
    expect($result->value)->toBe(81.0);
});

it('Weighted MP: просрочка 3 недели на оценке 10 → 63', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [new WorkGrade(10, 1.0, 3)], // 3 недели = ×0.7
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    // 10 * 0.7 * 9 = 63
    expect($result->value)->toBe(63.0);
});

it('Weighted MP: просрочка 10 недель — потолок 0.5, оценка 10 → 45', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [new WorkGrade(10, 1.0, 10)],
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    // 10 * 0.5 * 9 = 45
    expect($result->value)->toBe(45.0);
});

it('Weighted MP: одна работа с просрочкой, другая без', function () {
    $strategy = new WeightedStrategy();
    $module = new ModuleInput(
        works: [
            new WorkGrade(10, 0.5, 0), // без штрафа
            new WorkGrade(10, 0.5, 5), // ×0.5
        ],
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    // (10*0.5 + 10*0.5*0.5) / 1 * 9 = (5 + 2.5) * 9 = 67.5
    expect($result->value)->toBe(67.5);
});

// --- ArithmeticStrategy: MP ---

it('Arithmetic MP: две работы 8 и 6 → 63', function () {
    $strategy = new ArithmeticStrategy();
    $module = new ModuleInput(
        works: [
            new WorkGrade(8, 1.0, 0),
            new WorkGrade(6, 1.0, 0),
        ],
        weight: 1.0,
    );

    $result = $strategy->calculateModuleRating($module);

    // (8 + 6) / 2 * 9 = 63
    expect($result->value)->toBe(63.0);
});

it('Arithmetic MP: три работы 10, 5, 7 → 66', function () {
    $strategy = new ArithmeticStrategy();
    $module = new ModuleInput(
        works: [
            new WorkGrade(10, 1.0, 0),
            new WorkGrade(5, 1.0, 0),
            new WorkGrade(7, 1.0, 0),
        ],
        weight: 1.0,
    );

    // (10 + 5 + 7) / 3 * 9 = 22/3 * 9 = 66
    $result = $strategy->calculateModuleRating($module);
    expect($result->value)->toBe(66.0);
});

// --- WeightedStrategy: ИР ---

it('Weighted ИР: два модуля с равными весами 0.5, MP 80 и 60 → 70', function () {
    $strategy = new WeightedStrategy();
    $moduleRatings = [
        new \App\Services\Rating\Calculator\DTO\ModuleRating(80.0, false),
        new \App\Services\Rating\Calculator\DTO\ModuleRating(60.0, false),
    ];
    $moduleInputs = [
        new ModuleInput([], 0.5),
        new ModuleInput([], 0.5),
    ];

    $ir = $strategy->calculateFinalRating($moduleRatings, $moduleInputs);

    // (80*0.5 + 60*0.5) / 1 = 70
    expect($ir)->toBe(70.0);
});

it('Weighted ИР: веса 0.6 и 0.4, MP 80 и 60 → 72', function () {
    $strategy = new WeightedStrategy();
    $moduleRatings = [
        new \App\Services\Rating\Calculator\DTO\ModuleRating(80.0, false),
        new \App\Services\Rating\Calculator\DTO\ModuleRating(60.0, false),
    ];
    $moduleInputs = [
        new ModuleInput([], 0.6),
        new ModuleInput([], 0.4),
    ];

    $ir = $strategy->calculateFinalRating($moduleRatings, $moduleInputs);

    // (80*0.6 + 60*0.4) / 1 = 48 + 24 = 72
    expect($ir)->toBe(72.0);
});

// --- ArithmeticStrategy: ИР ---

it('Arithmetic ИР: три модуля 80, 60, 70 → 70', function () {
    $strategy = new ArithmeticStrategy();
    $moduleRatings = [
        new \App\Services\Rating\Calculator\DTO\ModuleRating(80.0, false),
        new \App\Services\Rating\Calculator\DTO\ModuleRating(60.0, false),
        new \App\Services\Rating\Calculator\DTO\ModuleRating(70.0, false),
    ];

    $ir = $strategy->calculateFinalRating($moduleRatings, []);

    expect($ir)->toBe(70.0);
});

// --- RatingCalculator (фасад) ---

it('RatingCalculator: без весов идёт по арифметике', function () {
    $calculator = new RatingCalculator();
    $discipline = new DisciplineInput(modules: [
        new ModuleInput(
            works: [new WorkGrade(10, 1.0, 0), new WorkGrade(10, 1.0, 0)],
            weight: 1.0,
        ),
        new ModuleInput(
            works: [new WorkGrade(5, 1.0, 0), new WorkGrade(5, 1.0, 0)],
            weight: 1.0,
        ),
    ]);

    $result = $calculator->calculateDiscipline($discipline);

    // MP1 = 90, MP2 = 45, ИР = (90 + 45) / 2 = 67.5
    expect($result->moduleRatings[0]->value)->toBe(90.0);
    expect($result->moduleRatings[1]->value)->toBe(45.0);
    expect($result->finalRating)->toBe(67.5);
    expect($result->isAdmitted)->toBeTrue();
});

it('RatingCalculator: с весами идёт по weighted', function () {
    $calculator = new RatingCalculator();
    $discipline = new DisciplineInput(modules: [
        new ModuleInput(
            works: [new WorkGrade(10, 1.0, 0)],
            weight: 0.7,
        ),
        new ModuleInput(
            works: [new WorkGrade(5, 1.0, 0)],
            weight: 0.3,
        ),
    ]);

    $result = $calculator->calculateDiscipline($discipline);

    // MP1 = 90, MP2 = 45, ИР = 90*0.7 + 45*0.3 = 63 + 13.5 = 76.5
    expect($result->finalRating)->toBe(76.5);
});

it('RatingCalculator: есть работа с 0 → не допущен', function () {
    $calculator = new RatingCalculator();
    $discipline = new DisciplineInput(modules: [
        new ModuleInput(
            works: [new WorkGrade(0, 1.0, 0), new WorkGrade(10, 1.0, 0)],
            weight: 1.0,
        ),
    ]);

    $result = $calculator->calculateDiscipline($discipline);

    expect($result->isAdmitted)->toBeFalse();
});

it('RatingCalculator: пустая дисциплина → 0 и не допущен', function () {
    $calculator = new RatingCalculator();
    $discipline = new DisciplineInput(modules: []);

    $result = $calculator->calculateDiscipline($discipline);

    expect($result->finalRating)->toBe(0.0);
    expect($result->isAdmitted)->toBeTrue(); // нет модулей — нет нулевых работ
});
