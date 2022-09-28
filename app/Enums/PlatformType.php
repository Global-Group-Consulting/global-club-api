<?php

namespace App\Enums;

abstract class PlatformType {
  const PUSH = "push";
  const EMAIL = "email";
  const APP = "app";
  const ALL = [self::PUSH, self::EMAIL, self::APP];
}
