<?php

namespace Rutatiina\JournalEntry\Services;

use Illuminate\Support\Facades\Validator;

class JournalEntryValidateService
{
    public static $errors = [];

    public static function run($requestInstance)
    {
        //$request = request(); //used for the flash when validation fails
        $user = auth()->user();

        /*
        $rules = [
            'items.*.debit' => ['numeric', 'gt:0', 'nullable'],
            'items.*.credit' => ['numeric', 'gt:0', 'nullable']
        ];

        $request->validate($rules);

        foreach ($data['recordings'] as &$recording)
        {
            if (isset($recording['debit']) && isset($recording['credit']))
            {
                if ($recording['debit'] > 0 && $recording['credit'] > 0)
                {
                    return response()->json([
                        'message' => 'Journal Error!',
                        'errors' => ['Both debit and credit cannot be set on the same item.']
                    ], 422);
                }
            }
        }
        unset($recording);
        //*/

        // >> data validation >>------------------------------------------------------------

        //validate the data
        $customMessages = [
            'recordings.*.financial_account_code.required' => "The account is required",
            'recordings.*.financial_account_code.numeric' => "The account must be numeric",
            'recordings.*.financial_account_code.gt' => "The account is required",

            'recordings.*.debit.numeric' => "The debit amount must be numeric",
            'recordings.*.credit.numeric' => "The credit amount must be numeric",

        ];

        $rules = [
            //'contact_id' => 'required|numeric',
            'date' => 'required|date',
            'currency' => 'required',
            'notes' => 'string|nullable',

            'recordings' => 'required|array',
            'recordings.*.financial_account_code' => 'required|numeric',
            'recordings.*.description' => 'required',
        ];

        $validator = Validator::make($requestInstance->all(), $rules, $customMessages);

        if ($validator->fails())
        {
            self::$errors = $validator->errors()->all();
            return false;
        }

        $debitTotal = 0;
        $creditTotal = 0;

        //validate the recordings debit and credit amount
        foreach ($requestInstance->recordings as $rowId => $recording)
        {
            $debitTotal += $recording['debit'];
            $creditTotal += $recording['credit'];

            //both deibt and credit connot be empty
            if (empty($recording['debit']) && empty($recording['credit']))
            {
                self::$errors[] = 'The debit or credit amount is required for row #'.(++$rowId);
                return false;
            }

            //both deibt and credit connot both be set
            if (!empty($recording['debit']) && !empty($recording['credit']))
            {
                self::$errors[] = 'Either debit or credit amount is required not both for row #'.(++$rowId);
                return false;
            }
        }

        //total debit has to equal to the total credit
        if ($debitTotal != $creditTotal)
        {
            self::$errors[] = 'The total debit amount has to be equal to the total credit amount.';
            return false;
        }

        // << data validation <<------------------------------------------------------------

        $data['id'] = $requestInstance->input('id', null); //for updating the id will always be posted
        $data['user_id'] = $user->id;
        $data['tenant_id'] = $user->tenant->id;
        $data['created_by'] = $user->name;
        $data['app'] = 'web';
        $data['number'] = $requestInstance->input('number');
        $data['date'] = $requestInstance->input('date');
        $data['reference'] = $requestInstance->input('reference', null);
        $data['currency'] =  $requestInstance->input('currency');
        $data['branch_id'] = $requestInstance->input('branch_id', null);
        $data['store_id'] = $requestInstance->input('store_id', null);
        $data['notes'] = $requestInstance->input('notes', null);
        $data['status'] = strtolower($requestInstance->input('status', null));
        $data['total'] = $debitTotal;
        $data['balances_where_updated'] = 0;


        //set the transaction total to zero
        $txnTotal = 0;
        $taxableAmount = 0;

        //Formulate the DB ready items array
        $data['recordings'] = [];
        foreach ($requestInstance->recordings as $key => $recording)
        {
            $data['recordings'][] = [
                'tenant_id' => $data['tenant_id'],
                'created_by' => $data['created_by'],
                'contact_id' => $recording['contact_id'],
                'financial_account_code' => $recording['financial_account_code'],
                'description' => $recording['description'],
                'debit' => $recording['debit'],
                'credit' => $recording['credit'],
            ];

            $total = (empty($recording['debit'])) ? $recording['credit'] : $recording['debit'];

            //ledgers
            $data['ledgers'][ $recording['financial_account_code']]['financial_account_code'] =  $recording['financial_account_code'];
            $data['ledgers'][ $recording['financial_account_code']]['effect'] = (empty($recording['debit'])) ? 'credit' : 'debit';
            $data['ledgers'][ $recording['financial_account_code']]['total'] = @$data['ledgers'][ $recording['financial_account_code']]['total'] + $total;
            $data['ledgers'][ $recording['financial_account_code']]['contact_id'] = $recording['contact_id'];
        }

        //print_r($data['ledgers']); exit;

        //Now add the default values to items and ledgers

        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['tenant_id'] = $data['tenant_id'];
            $ledger['date'] = date('Y-m-d', strtotime($data['date']));
            $ledger['base_currency'] = $data['currency'];
            $ledger['quote_currency'] = $data['currency'];
            $ledger['exchange_rate'] = 1;
        }
        unset($ledger);

        //Return the array of txns
        //print_r($data); exit;

        return $data;

    }

}
