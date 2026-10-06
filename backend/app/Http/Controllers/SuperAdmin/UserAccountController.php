<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleKind;

class UserAccountController extends AccountController
{
    protected function kind(): RoleKind
    {
        return RoleKind::User;
    }

    protected function routePrefix(): string
    {
        return 'superadmin.users';
    }
}
