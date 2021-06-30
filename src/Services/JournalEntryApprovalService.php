<?php

namespace Rutatiina\JournalEntry\Services;

use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

trait JournalEntryApprovalService
{
    public static function run($txn)
    {
        if ($txn['status'] != 'approved')
        {
            //can only update balances if status is approved
            return false;
        }

        if (isset($txn['balances_where_updated']) && $txn['balances_where_updated'])
        {
            //cannot update balances for task already completed
            return false;
        }

        //inventory checks and inventory balance update if needed
        //$this->inventory(); //currently inventory update for estimates is disabled

        //Update the account balances
        AccountBalanceUpdateService::doubleEntry($txn);

        //Update the contact balances
        ContactBalanceUpdateService::doubleEntry($txn);

        $txn->status = 'approved';
        $txn->balances_where_updated = 1;
        $txn->save();

        return true;
    }

}
