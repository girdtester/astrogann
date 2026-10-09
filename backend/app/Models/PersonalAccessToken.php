<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum token that writes last_used_at at most every 5 minutes instead of on every API request (D-96).
 * With 1 lakh app users checking for notifications this removes one database write per request.
 */
class PersonalAccessToken extends SanctumToken
{
    protected $table = 'personal_access_tokens';

    public function save(array $options = []): bool
    {
        $dirty = array_keys($this->getDirty());

        if ($this->exists && $dirty === ['last_used_at']) {
            $previous = $this->getOriginal('last_used_at');
            if ($previous && now()->diffInSeconds($previous, true) < 300) {
                $this->syncOriginalAttribute('last_used_at'); // skip the write, keep the model clean

                return true;
            }
        }

        return parent::save($options);
    }
}
