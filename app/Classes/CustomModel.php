<?php

namespace App\Classes;

use Jenssegers\Mongodb\Eloquent\Model;

class CustomModel extends Model {
  /**
   * The name of the "created at" column.
   *
   * @var string|null
   */
  const CREATED_AT = 'createdAt';
  
  /**
   * The name of the "updated at" column.
   *
   * @var string|null
   */
  const UPDATED_AT = 'updatedAt';
  
}
