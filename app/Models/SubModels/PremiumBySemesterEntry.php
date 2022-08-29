<?php

namespace App\Models\SubModels;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * @mixin Builder
 *
 * @property string $_id
 * @property array  $inMovements
 * @property float  $inAmount
 * @property float  $outAmount
 * @property array  $outMovements
 * @property float  $remainingAmount
 * @property string $semester
 * @property User   $user
 */
class PremiumBySemesterEntry extends Model {
  
  protected $fillable = [
    "_id",
    "inMovements",
    "inAmount",
    "outAmount",
    "outMovements",
    "remainingAmount",
    "semester",
  ];
  
  public function user(): BelongsTo {
    return $this->belongsTo(User::class, "_id", "_id");
  }
}
