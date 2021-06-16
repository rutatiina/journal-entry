<?php

namespace Rutatiina\JournalEntry\Classes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Rutatiina\JournalEntry\Models\JournalEntry;
use Rutatiina\JournalEntry\Models\JournalEntryLedger;
use Rutatiina\JournalEntry\Models\JournalEntryRecording;
use Rutatiina\JournalEntry\Models\JournalEntryRecurring;
use Rutatiina\JournalEntry\Traits\Init as TxnTraitsInit;
use Rutatiina\JournalEntry\Traits\TxnItemsJournalLedgers as TxnTraitsTxnItemsJournalLedgers;
use Rutatiina\JournalEntry\Traits\TxnTypeBasedSpecifics as TxnTraitsTxnTypeBasedSpecifics;
use Rutatiina\JournalEntry\Traits\Validate as TxnTraitsValidate;
use Rutatiina\JournalEntry\Traits\AccountBalanceUpdate as TxnTraitsAccountBalanceUpdate;
use Rutatiina\JournalEntry\Traits\ContactBalanceUpdate as TxnTraitsContactBalanceUpdate;
use Rutatiina\JournalEntry\Traits\Approve as TxnTraitsApprove;

class Store
{
    use TxnTraitsInit;
    use TxnTraitsTxnItemsJournalLedgers;
    use TxnTraitsTxnTypeBasedSpecifics;
    use TxnTraitsValidate;
    use TxnTraitsAccountBalanceUpdate;
    use TxnTraitsContactBalanceUpdate;
    use TxnTraitsApprove;

    public function __construct()
    {}

    public function run()
    {
        //print_r($this->txnInsertData); exit;

        $verifyWebData = $this->validate();
        if ($verifyWebData === false) return false;

        //check if inventory is affected and if its available
        //for the mean time inventory functions are disabled for estimates
        //$inventoryAvailability = $this->inventoryAvailability();
        //if ($inventoryAvailability === false) return false;

		//Log::info($this->txn);
        //var_dump($this->txn); exit;
        //print_r($this->txn); exit;
        //echo json_encode($this->txn); exit;

        //print_r($this->txn); exit; //$this->txn, $this->txn['items'], $this->txn[number], $this->txn[ledgers], $this->txn[recurring]

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try {

            //print_r($this->txn); exit;
            $Txn = new JournalEntry;
            $Txn->tenant_id = $this->txn['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->date = $this->txn['date'];
            $Txn->reference = $this->txn['reference'];
            $Txn->currency = $this->txn['currency'];
            $Txn->total = $this->txn['total'];
            $Txn->branch_id = $this->txn['branch_id'];
            $Txn->store_id = $this->txn['store_id'];
            $Txn->status = $this->txn['status'];

            $Txn->save();
            $this->txn['id'] = $Txn->id;

            foreach($this->txn['recordings'] as &$recording)
            {
                $recording['journal_entry_id'] = $this->txn['id'];
            }

            unset($recording);

            //print_r($this->txn['items']); exit;

            foreach($this->txn['ledgers'] as &$ledger)
            {
                $ledger['journal_entry_id'] = $this->txn['id'];
            }
            unset($ledger);

            //Save the items >> $this->txn['items']
            JournalEntryRecording::insert($this->txn['recordings']);

            //Save the ledgers >> $this->txn['ledgers']; and update the balances
            JournalEntryLedger::insert($this->txn['ledgers']);

            //save transaction recurring details
            if ($this->txn['isRecurring'] === true)
            {
                $TxnRecurring = new JournalEntryRecurring;
                $TxnRecurring->tenant_id = $this->txn['tenant_id'];
                $TxnRecurring->journal_entry_id = $this->txn['id'];
                $TxnRecurring->frequency = $this->txn['recurring']['frequency'];
                //$TxnRecurring->measurement = $this->txn['recurring']['frequency']; //of no use
                $TxnRecurring->start_date = $this->txn['recurring']['start_date'];
                $TxnRecurring->end_date = $this->txn['recurring']['end_date'];
                $TxnRecurring->day_of_month = $this->txn['recurring']['day_of_month'];
                $TxnRecurring->month = $this->txn['recurring']['month'];
                $TxnRecurring->day_of_week = $this->txn['recurring']['day_of_week'];
                $TxnRecurring->save();

            }

            $this->approve();


            DB::connection('tenant')->commit();

            return (object) [
                'id' => $this->txn['id'],
            ];

        } catch (\Exception $e) {

            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to save Journal Entry to database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local')) {
                $this->errors[] = 'Error: Failed to save Journal Entry to database.';
                $this->errors[] = 'File: '. $e->getFile();
                $this->errors[] = 'Line: '. $e->getLine();
                $this->errors[] = 'Message: ' . $e->getMessage();
            } else {
                $this->errors[] = 'Fatal Internal Error: Failed to save Journal Entry to database. Please contact Admin';
			}

            return false;
        }

    }

}
