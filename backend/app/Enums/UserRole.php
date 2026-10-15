<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case StudioAdmin = 'studio_admin';
    case Trainer = 'trainer';
    case Customer = 'customer';
}
