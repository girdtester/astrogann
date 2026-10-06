<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleKind;

class AdminAccountController extends AccountController
{
    protected function kind(): RoleKind
    {
        return RoleKind::Staff;
    }

    protected function routePrefix(): string
    {
        return 'superadmin.admins';
    }
}
