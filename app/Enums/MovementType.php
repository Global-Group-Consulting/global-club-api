<?php

namespace App\Enums;

enum MovementType {
  // Generated when recapitalization occurs
  const INTEREST_RECAPITALIZED = "interest_recapitalized";
  
  // When added by admins
  const DEPOSIT_ADDED = "deposit_added";
  
  // When removed by admins
  const DEPOSIT_REMOVED = "deposit_removed";
  
  // When a user transfers them to a user
  const DEPOSIT_TRANSFERRED = "deposit_transferred";
  
  // When a user uses them
  const DEPOSIT_USED = "deposit_used";
  
  const IN_MOVEMENTS = [self::INTEREST_RECAPITALIZED, self::DEPOSIT_ADDED];
  const OUT_MOVEMENTS = [self::DEPOSIT_REMOVED, self::DEPOSIT_TRANSFERRED, self::DEPOSIT_USED];
}


