<?php

namespace App\Policies;

use App\Models\User;

class ComponentPolicy extends CheckoutablePermissionsPolicy
{
    protected function columnName()
    {
        return 'components';
    }

    public function files(User $user, $item = null)
    {
        return $user->hasAccess($this->columnName().'.files');
    }

    /**
     * Determine whether the user can checkout component items.
     */
    public function checkout(User $user, $item = null)
    {
        return $user->hasAccess($this->columnName().'.checkout');
    }

    /**
     * Determine whether the user can checkin component items.
     */
    public function checkin(User $user, $item = null)
    {
        return $user->hasAccess($this->columnName().'.checkin');
    }
}
