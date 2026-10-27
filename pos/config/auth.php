<?php

use Pos\Models\Staff;

return ['defaults' => ['guard' => 'web'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'staff']], 'providers' => ['staff' => ['driver' => 'eloquent', 'model' => Staff::class]], 'password_timeout' => 300];
