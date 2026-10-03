<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('intsec.security', function ($user): bool {
    return $user->isAdministrator();
});
