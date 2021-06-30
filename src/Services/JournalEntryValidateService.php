<?php

namespace Rutatiina\JournalEntry\Services;

use Illuminate\Support\Facades\Validator;
use Rutatiina\Contact\Models\Contact;

class JournalEntryValidateService
{
    public static $errors = [];

    public static function run($requestInstance)
    {
        //$request = request(); //used for the flash when validation fails
        $user = auth()->user();


        // >> data validation >>------------------------------------------------------------

        //validate the data
        $customMessages = [
            'items.*.debit_financial_account_code.required' => "The item account is required",
            'items.*.debit_financial_account_code.numeric' => "The item account must be numeric",
            'items.*.debit_financial_account_code.gt' => "The item account is required",
            'items.*.taxes.*.code.required' => "Tax code is required",
            'items.*.taxes.*.total.required' => "Tax total is required",
        ];

        $rules = [
            'contact_id' => 'required|numeric',
            'date' => 'required|date',
            'base_currency' => 'required',
            'contact_notes' => 'string|nullable',

            'items' => 'required|array',
            'items.*.name' => 'required_without:item_id',
            'items.*.rate' => 'required|numeric',
            'items.*.quantity' => 'required|numeric|gt:0',
            //'items.*.total' => 'required|numeric|in:' . $itemTotal, //todo custom validator to check this
            'items.*.units' => 'numeric|nullable',
            'items.*.debit_financial_account_code' => 'required|numeric|gt:0',
            'items.*.taxes' => 'array|nullable',
            'items.*.taxes.*.code' => 'required',
            'items.*.taxes.*.total' => 'required|numeric',
            //'items.*.taxes.*.exclusive' => 'required|numeric',
        ];

        $validator = Validator::make($requestInstance->all(), $rules, $customMessages);

        if ($validator->fails())
        {
            self::$errors = $validator->errors()->all();
            return false;
        }

        // << data validation <<------------------------------------------------------------

        $contact = Contact::findOrFail($requestInstance->contact_id);


        $data['id'] = $requestInstance->input('id', null); //for updating the id will always be posted
        $data['user_id'] = $user->id;
        $data['tenant_id'] = $user->tenant->id;
        $data['created_by'] = $user->name;
        $data['app'] = 'web';
        $data['number'] = $requestInstance->input('number');
        $data['date'] = $requestInstance->input('date');
        $data['contact_id'] = $requestInstance->contact_id;
        $data['contact_name'] = $contact->name;
        $data['contact_address'] = trim($contact->shipping_address_street1 . ' ' . $contact->shipping_address_street2);
        $data['reference'] = $requestInstance->input('reference', null);
        $data['base_currency'] =  $requestInstance->input('base_currency');
        $data['quote_currency'] =  $requestInstance->input('quote_currency', $data['base_currency']);
        $data['exchange_rate'] = $requestInstance->input('exchange_rate', 1);
        $data['branch_id'] = $requestInstance->input('branch_id', null);
        $data['store_id'] = $requestInstance->input('store_id', null);
        $data['due_date'] = $requestInstance->input('due_date', null);
        $data['terms_and_conditions'] = $requestInstance->input('terms_and_conditions', null);
        $data['contact_notes'] = $requestInstance->input('contact_notes', null);
        $data['status'] = strtolower($requestInstance->input('status', null));
        $data['balances_where_updated'] = 0;


        //set the transaction total to zero
        $txnTotal = 0;
        $taxableAmount = 0;

        //Formulate the DB ready items array
        $data['items'] = [];
        foreach ($requestInstance->items as $key => $item)
        {
            $itemTaxes = $requestInstance->input('items.'.$key.'.taxes', []);

            $txnTotal           += ($item['rate']*$item['quantity']);
            $taxableAmount      += ($item['rate']*$item['quantity']);
            $itemTaxableAmount   = ($item['rate']*$item['quantity']); //calculate the item taxable amount

            foreach ($itemTaxes as $itemTax)
            {
                $txnTotal           += $itemTax['exclusive'];
                $taxableAmount      -= $itemTax['inclusive'];
                $itemTaxableAmount  -= $itemTax['inclusive']; //calculate the item taxable amount more by removing the inclusive amount
            }

            //use item selling_financial_account_code if available and default if not
            $financialAccountToDebit = $item['debit_financial_account_code'];

            $data['items'][] = [
                'tenant_id' => $data['tenant_id'],
                'created_by' => $data['created_by'],
                'contact_id' => $item['contact_id'],
                'item_id' => $item['item_id'],
                'debit_financial_account_code' => $financialAccountToDebit,
                'name' => $item['name'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'rate' => $item['rate'],
                'total' => $item['total'],
                'taxable_amount' => $itemTaxableAmount,
                'units' => $requestInstance->input('items.'.$key.'.units', null),
                'batch' => $requestInstance->input('items.'.$key.'.batch', null),
                'expiry' => $requestInstance->input('items.'.$key.'.expiry', null),
                'taxes' => $itemTaxes,
            ];

            //DR ledger
            $data['ledgers'][$financialAccountToDebit]['financial_account_code'] = $financialAccountToDebit;
            $data['ledgers'][$financialAccountToDebit]['effect'] = 'debit';
            $data['ledgers'][$financialAccountToDebit]['total'] = @$data['ledgers'][$financialAccountToDebit]['total'] + $itemTaxableAmount;
            $data['ledgers'][$financialAccountToDebit]['contact_id'] = $data['contact_id'];
        }

        $data['taxable_amount'] = $taxableAmount;
        $data['total'] = $txnTotal;


        //CR ledger
        $data['ledgers'][] = [
            'financial_account_code' => null, //todo
            'effect' => 'credit',
            'total' => $data['total'],
            'contact_id' => $data['contact_id']
        ];

        //print_r($data['ledgers']); exit;

        //Now add the default values to items and ledgers

        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['tenant_id'] = $data['tenant_id'];
            $ledger['date'] = date('Y-m-d', strtotime($data['date']));
            $ledger['base_currency'] = $data['base_currency'];
            $ledger['quote_currency'] = $data['quote_currency'];
            $ledger['exchange_rate'] = $data['exchange_rate'];
        }
        unset($ledger);

        //Return the array of txns
        //print_r($data); exit;

        return $data;

    }

}
