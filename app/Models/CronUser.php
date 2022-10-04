<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Auth\User as Authenticatable;

class CronUser extends Authenticatable {
  use HasFactory;
  
  protected $connection = "mongodb_legacy";
  
}
