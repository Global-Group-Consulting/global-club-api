<?php

test("[round] - round() function in action", function () {
  expect(round(1.2345))->toBe(1.0);
  expect(round(1.5345))->toBe(2.0);
  expect(round(0.9))->toBe(1.0);
  expect(round(0.5))->toBe(1.0);
  expect(round(0.3))->toBe(0.0);
  expect(round(0.08))->toBe(0.0);
  expect(round(0.02))->toBe(0.0);
  expect(round(0.08827))->toBe(0.0);
});
