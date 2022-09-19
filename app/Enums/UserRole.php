<?php

namespace App\Enums;

enum UserRole {
  const ADMIN = "admin";
  const SUPER_ADMIN = "super_admin";
  const CLIENTS_SERVICE = "clients_service";
  const AGENT = "agent";
  const CLIENT = "client";
  const CLUB_ADMIN = "admin_club";
}
