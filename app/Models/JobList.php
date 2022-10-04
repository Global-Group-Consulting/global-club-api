<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * App\Models\JobListController
 *
 * @mixin Builder
 * @property int         $id
 * @property string      $title
 * @property string      $description
 * @property string      $class
 * @property string      $payloadValidation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string      $payloadKey
 * @property string|null $apiUrl
 * @property string|null $apiMethod
 * @property string|null $apiHeaders
 * @property string|null $authType
 * @property string|null $authUsername
 * @property string|null $authPassword
 * @property string      $queueName
 */
class JobList extends Model {
  use HasFactory;
  
  protected $connection = 'mysql';
  
  protected $fillable = [
    "title",
    "description",
    "class",
    "queueName",
    "payloadKey",
    "payloadValidation",
    "apiUrl",
    "apiMethod",
    "apiHeaders",
    "authType",
    "authPassword",
    "authUsername",
  ];
}
