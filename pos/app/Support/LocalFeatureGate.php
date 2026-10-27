<?php

namespace Pos\Support;

use Illuminate\Http\Request;

class LocalFeatureGate
{
    public static function ensure(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.synthetic_prescriptions_enabled') === true, 503, 'Synthetic prescription development is disabled.');
    }

    public static function request(Request $request): void
    {
        self::ensure();
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 403, 'Local fixture access required.');
    }
}
