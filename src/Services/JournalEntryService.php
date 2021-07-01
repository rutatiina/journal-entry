<?php

namespace Rutatiina\JournalEntry\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rutatiina\JournalEntry\Models\JournalEntry;
use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;
use Rutatiina\JournalEntry\Models\JournalEntrySetting;
use Rutatiina\Tax\Models\Tax;

class JournalEntryService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function nextNumber()
    {
        $count = JournalEntry::count();
        $settings = JournalEntrySetting::first();

        return $settings->number_prefix . (str_pad(($count + 1), $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix;
    }

    public static function edit($id)
    {
        $txn = JournalEntry::findOrFail($id);
        $txn->load('contact', 'recordings');

        $attributes = $txn->toArray();

        //print_r($attributes); exit;

        $attributes['_method'] = 'PATCH';

        foreach ($attributes['recordings'] as &$recording)
        {
            $recording['selectedItem'] = []; #required
            $recording['selectedTaxes'] = []; #required
            $recording['displayTotal'] = 0; #required
            $recording['debit'] = floatval($recording['debit']);
            $recording['credit'] = floatval($recording['credit']);
            $recording['contact_id'] = floatval($recording['contact_id']);
            $recording['financial_account_code'] = floatval($recording['financial_account_code']);
        };

        return $attributes;
    }

    public static function store($requestInstance)
    {
        $data = JournalEntryValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = JournalEntryValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = new JournalEntry;
            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            //$Txn->document_name = $data['document_name'];
            $Txn->number = $data['number'];
            $Txn->date = $data['date'];
            $Txn->reference = $data['reference'];
            $Txn->currency = $data['currency'];
            $Txn->total = $data['total'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            $Txn->notes = $data['notes'];
            $Txn->status = $data['status'];

            $Txn->save();

            $data['id'] = $Txn->id;


            //Save the recordings >> $data['recordings']
            $Txn->recordings()->createMany($data['recordings']);

            //Save the ledgers >> $data['ledgers']; and update the balances
            $Txn->ledgers()->createMany($data['ledgers']);

            //$Txn->refresh(); //make the ledgers relationship infor available

            //check status and update financial account and contact balances accordingly
            JournalEntryApprovalService::run($Txn);

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to save JournalEntry to database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to save JournalEntry to database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to save JournalEntry to database. Please contact Admin';
            }

            return false;
        }
        //*/

    }

    public static function update($requestInstance)
    {
        $data = JournalEntryValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = JournalEntryValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = JournalEntry::with('ledgers', 'recordings')->findOrFail($data['id']);

            if ($Txn->status == 'approved')
            {
                self::$errors[] = 'Approved JournalEntry cannot be not be edited';
                return false;
            }

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($Txn->toArray(), true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($Txn->toArray(), true);

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->recordings()->delete();
            $Txn->comments()->delete();
            $Txn->delete();

            $txnStore = self::store($requestInstance);

            DB::connection('tenant')->commit();

            return $txnStore;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to update JournalEntry in database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to update JournalEntry in database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to update JournalEntry in database. Please contact Admin';
            }

            return false;
        }

    }

    public static function destroy($id)
    {
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = JournalEntry::with('items', 'ledgers')->findOrFail($id);

            if ($Txn->status == 'approved')
            {
                self::$errors[] = 'Approved JournalEntry cannot be not be deleted';
                return false;
            }

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->recordings()->delete();
            $Txn->comments()->delete();

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($Txn, true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($Txn, true);

            $Txn->delete();

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to delete JournalEntry from database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to delete JournalEntry from database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to delete JournalEntry from database. Please contact Admin';
            }

            return false;
        }
    }

    public static function copy($id)
    {
        $taxes = Tax::all()->keyBy('code');

        $txn = JournalEntry::findOrFail($id);
        $txn->load('contact', 'recordings');
        $txn->setAppends(['taxes']);

        $attributes = $txn->toArray();

        #reset some values
        $attributes['number'] = self::nextNumber();
        $attributes['date'] = date('Y-m-d');
        #reset some values

        $attributes['contact']['currency'] = $attributes['contact']['currency_and_exchange_rate'];
        $attributes['contact']['currencies'] = $attributes['contact']['currencies_and_exchange_rates'];

        $attributes['taxes'] = json_decode('{}');

        foreach ($attributes['items'] as &$item)
        {
            $selectedItem = [
                'id' => $item['item_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'rate' => $item['rate'],
                'tax_method' => 'inclusive',
                'account_type' => null,
            ];

            $item['selectedItem'] = $selectedItem; #required
            $item['selectedTaxes'] = []; #required
            $item['displayTotal'] = 0; #required
            $item['rate'] = floatval($item['rate']);
            $item['quantity'] = floatval($item['quantity']);
            $item['total'] = floatval($item['total']);
            $item['displayTotal'] = $item['total']; #required

            foreach ($item['taxes'] as $itemTax)
            {
                $item['selectedTaxes'][] = $taxes[$itemTax['tax_code']];
            }
        };
        unset($item);

        return $attributes;
    }

    public static function approve($id)
    {
        $Txn = JournalEntry::with(['ledgers'])->findOrFail($id);

        if (!in_array($Txn->status, config('financial-accounting.approvable_status')))
        {
            self::$errors[] = $Txn->status . ' JournalEntry cannot be approved';
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn->status = 'approved';
            JournalEntryApprovalService::run($Txn);

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Exception $e)
        {
            DB::connection('tenant')->rollBack();
            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'DB Error: Failed to approve JournalEntry.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to approve JournalEntry. Please contact Admin';
            }

            return false;
        }
    }

}
