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
  
  // When a user transfer brites from premium wallet to normal wallet of another user
  const DEPOSIT_RECEIVED_WP = "deposit_received_wp";
  
  // When a user unlock brites from premium wallet to its own normal wallet
  const DEPOSIT_UNLOCKED_WP = "deposit_unlocked_wp";
  
  const IN_MOVEMENTS = [self::INTEREST_RECAPITALIZED, self::DEPOSIT_ADDED];
  const OUT_MOVEMENTS = [self::DEPOSIT_REMOVED, self::DEPOSIT_TRANSFERRED, self::DEPOSIT_USED];
}


