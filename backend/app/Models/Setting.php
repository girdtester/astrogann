<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value system settings. Read through App\Services\SettingsService (cached).
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];
}
