<?php

namespace App\Services;

use App\Models\Transaction;

class TransactionNumber
{
    public function next(): string
    {
        $prefix = 'TRX-'.now()->format('Ymd').'-';
        $sequence = 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Transaction::query()->where('transaction_number', $number)->exists());

        return $number;
    }
}
