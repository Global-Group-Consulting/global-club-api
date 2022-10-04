<?php

use App\Models\SubModels\Semester;

test("[parse()] - throws error on invalid argument", function () {
  expect(fn() => Semester::parse("2020-1"))
    ->toThrow(\InvalidArgumentException::class, "Invalid semester format");
});

test('[parse()] - returning array contains right keys', function () {
  $semesterUsage = Semester::parse("2020_1");
  expect($semesterUsage)->toHaveKeys(['usableFrom', 'usableUntil', "id", "year", "semester", "walletPremium"]);
  
  $semesterUsage = Semester::parse("2020_2");
  expect($semesterUsage)->toHaveKeys(['usableFrom', 'usableUntil', "id", "year", "semester", "walletPremium"]);
});

test('[parse()] - usableFrom date', function () {
  $semesterUsage = Semester::parse("2020_1");
  expect($semesterUsage["usableFrom"]->toAtomString())->toBe((new \Carbon\Carbon("2020-07-01 00:00:00"))->toAtomString());
  
  $semesterUsage = Semester::parse("2020_2");
  expect($semesterUsage["usableFrom"]->toAtomString())->toBe((new \Carbon\Carbon("2021-01-01 00:00:00"))->toAtomString());
});

test('[parse()] - usableUntil date', function () {
  $semesterUsage = Semester::parse("2020_1");
  expect($semesterUsage["usableUntil"]->toAtomString())->toBe((new \Carbon\Carbon("2021-06-30 23:59:59"))->endOfDay()->toAtomString());
  
  $semesterUsage = Semester::parse("2022_2");
  expect($semesterUsage["usableUntil"]->toAtomString())->toBe((new \Carbon\Carbon("2023-12-31 23:59:59"))->endOfDay()->toAtomString());
});

test("[getCurrentSemester()] - return the current semester", function () {
  $semester = Semester::getCurrentSemester();
  $now      = \Carbon\Carbon::now();
  
  expect($semester)->toBeInstanceOf(Semester::class);
  expect($semester->id)->toBe($now->year . "_" . ($now->month < 7 ? 1 : 2));
});

test("[getPrevSemester()] - return the previous semester", function () {
  $semester = Semester::getPrevSemester("2020_1");
  expect($semester->id)->toBe("2019_2");
  
  $semester = Semester::getPrevSemester("2022_2");
  expect($semester->id)->toBe("2022_1");
});
