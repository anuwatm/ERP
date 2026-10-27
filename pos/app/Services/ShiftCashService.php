<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;

class ShiftCashService
{
    /** Caller holds the terminal/shift locks when recording or closing cash. */
    public function balance(object $shift): string
    {
        $balance = bcadd((string) $shift->opening_cash, '0', 2);
        foreach (DB::table('pos_orders')->where('shift_id', $shift->id)->where('status', 'completed')->pluck('total') as $amount) {
            $balance = bcadd($balance, (string) $amount, 2);
        }
        foreach (DB::table('pos_refunds')->where('shift_id', $shift->id)->pluck('total') as $amount) {
            $balance = bcsub($balance, (string) $amount, 2);
        }
        foreach (DB::table('pos_cash_movements')->where('shift_id', $shift->id)->get(['direction', 'amount']) as $move) {
            $balance = $move->direction === 'in' ? bcadd($balance, (string) $move->amount, 2) : bcsub($balance, (string) $move->amount, 2);
        }

        return $balance;
    }
}
