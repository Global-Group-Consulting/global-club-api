<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Builder;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * @mixin Builder
 *
 * @property string                              code
 * @property string                              title
 * @property string                              description
 * @property array{client: mixed, server: mixed} secrets
 * @property string                              emailsFrom
 */
class App extends Model {
  use HasFactory;
  
  protected $connection = "mongodb_iam";
  
}
