<?php

use Illuminate\Support\Str;

include 'vendor/autoload.php';

$match = Str::match("/^([0-9]{4})_(1|2)$/", "2020_1");

if($match)
{
  echo "ok";
}
