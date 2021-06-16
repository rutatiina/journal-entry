<?php

namespace Rutatiina\JournalEntry\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Rutatiina\Contact\Models\Contact;
use Rutatiina\JournalEntry\Models\JournalEntry;

trait Validate
{
    private function insertDataDefault($key, $defaultValue)
    {
        if (isset($this->txnInsertData[$key])) {
            return $this->txnInsertData[$key];
        } else {
            return $defaultValue;
        }
    }

    private function itemDataDefault($item, $key, $defaultValue)
    {
        if (isset($item[$key])) {
            return $item[$key];
        } else {
            return $defaultValue;
        }
    }

    private function validate()
    {
        //$request = request(); //used for the flash when validation fails
        $user = auth()->user();

        //print_r($request->all()); exit;

        $data = $this->txnInsertData;

        //print_r($data); exit;

        $data['user_id'] = $user->id;
        $data['tenant_id']  = $user->tenant->id;
        $data['created_by'] = $user->name;

        //set default values ********************************************
        $data['id'] = $this->insertDataDefault('id', null);
        $data['app'] = $this->insertDataDefault('app', null);
        $data['app_id'] = $this->insertDataDefault('app_id', null);
        $data['internal_ref'] = $this->insertDataDefault('internal_ref', null);

        $data['currency'] = $this->insertDataDefault('currency', null);

        $data['branch_id'] = $this->insertDataDefault('branch_id', null);
        $data['store_id'] = $this->insertDataDefault('store_id', null);
        $data['reference'] = $this->insertDataDefault('reference', null);
        $data['recurring'] = $this->insertDataDefault('recurring', []);

        $data['recordings'] = $this->insertDataDefault('recordings', []);

        $data['taxes'] = $this->insertDataDefault('taxes', []);

        $data['isRecurring'] = $this->insertDataDefault('isRecurring', false);

        // >> data validation >>------------------------------------------------------------

        //validate the data
        $rules = [
            'id' => 'numeric|nullable',
            'tenant_id' => 'required|numeric',
            'user_id' => 'required|numeric',
            'date' => 'required|date',
            'reference' => 'required|string',
            'currency' => 'required',
            'recordings' => 'required|array',

            'recordings.*.contact_id' => 'numeric|nullable',
            'recordings.*.financial_account_code' => 'required|numeric',
            'recordings.*.description' => 'required|string',
            'recordings.*.debit' => 'required|numeric',
            'recordings.*.credit' => 'required|numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $this->errors = $validator->errors()->all();
            return false;
        }


        //validate the recordings
		$customMessages = [
			'total.in' => "Item total is invalid:\nItem total = item rate x item quantity",
			'type_id.required_without' => "No item is selected in row.",
		];

        $recordings_total_debit = 0;
        $recordings_total_credit = 0;

        foreach ($data['recordings'] as $key => &$recording)
        {
            $recording['currency'] = $data['currency'];

            $recordings_total_debit += floatval($recording['debit']);
            $recordings_total_credit += floatval($recording['credit']);

            $contact = Contact::find($recording['contact_id']);
            if ($contact)
            {
                $recording['contact_name']       = (!empty(trim($contact->name))) ? $contact->name : $contact->display_name;
                $recording['contact_address']    = trim($contact->shipping_address_street1.' '.$contact->shipping_address_street2);
            }
            else
            {
                $recording['contact_id'] = null;
                $recording['contact_name'] = null;
                $recording['contact_address'] = null;
            }

        }
        //print_r($data['recordings']); exit;
        unset($recording);

        if ($recordings_total_debit != $recordings_total_credit)
        {
            $this->errors[] = 'Total debit amount is not equal to total credit amount.';
            return false;
        }

        //validate the recurring details
        if ($data['isRecurring'] === true || $data['isRecurring'] == 'true')
        {
            $validator = Validator::make($data['recurring'], [
                //'date_range' => 'required|array',
                'frequency' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'day_of_month' => 'required|string',
                'month' => 'required|string',
                'day_of_week' => 'required|string',
            ], $customMessages);

            if ($validator->fails()) {
                $this->errors = $validator->errors()->all();
                return false;
            }

            $this->txn['isRecurring'] = true;

        }
        else
        {
            $this->txn['isRecurring'] = false;
        }

        // << data validation <<------------------------------------------------------------


        //Formulate the DB ready recordings array
        $ledgers = [];
        $recordings = [];
        foreach ($data['recordings'] as $recording) {

            $recordingData = [
                'tenant_id'         => $data['tenant_id'],
                'financial_account_code'        => $recording['financial_account_code'],
                'contact_id'        => $recording['contact_id'],
                'contact_name'      => $recording['contact_name'],
                'contact_address'   => $recording['contact_address'],
                'description'       => $recording['description'],
                'currency'          => $recording['currency'],
                'debit'             => $recording['debit'],
                'credit'            => $recording['credit'],
            ];

            $recordings[] = $recordingData;

            if (floatval($recording['debit']) > 0)
            {
                $ledgers[] = [
                    'financial_account_code'    => $recording['financial_account_code'],
                    'effect'        => 'debit',
                    'total'         => $recording['debit'],
                    'contact_id'    => $recording['financial_account_code']
                ];
            }

            if (floatval($recording['credit']) > 0)
            {
                $ledgers[] = [
                    'financial_account_code'    => $recording['financial_account_code'],
                    'effect'        => 'credit',
                    'total'         => $recording['credit'],
                    'contact_id'    => $recording['financial_account_code']
                ];
            }

        }

        //print_r($data); exit;

        // >> Generate the transaction variables
        $this->txn['id'] = $data['id'];
        $this->txn['tenant_id'] = $data['tenant_id'];
        $this->txn['user_id'] = $data['user_id'];
        $this->txn['app'] = $data['app'];
        $this->txn['app_id'] = $data['app_id'];
        $this->txn['created_by'] = $data['created_by'];
        $this->txn['internal_ref'] = $data['internal_ref'];
        $this->txn['date'] = $data['date'];
        $this->txn['reference'] = $data['reference'];
        $this->txn['currency'] = $data['currency'];
        $this->txn['total'] = $recordings_total_debit;
        $this->txn['branch_id'] = $data['branch_id'];
        $this->txn['store_id'] = $data['store_id'];
        $this->txn['status'] = $data['status'];
        // << Generate the transaction variables

        $this->txn['recordings'] = $recordings;

        //$this->txn['accounts']    = []; //todo this line is to be deleted

        $this->txn['ledgers']    = $ledgers;

        $this->txn['recurring']  = $data['recurring'];

        //print_r($this->txn); exit;

        //Now add the default values to recordings and ledgers

        $this->txn['recordings_contacts_ids'] = [];

        foreach($this->txn['recordings'] as $item_index => &$recording)
        {
            if (isset($recording['contact_id']) && !empty($recording['contact_id']) && is_numeric($recording['contact_id']))
            {
                $this->txn['recordings_contacts_ids'][$recording['contact_id']] = $recording['contact_id'];
            }
        }
        unset($recording);

        $this->txnItemsJournalLedgers(); //this must always come last because it resets the ledgers

        foreach($this->txn['ledgers'] as $ledgers_index => &$ledger)
        {
            $ledger['tenant_id']        = $data['tenant_id'];
            $ledger['date']             = date('Y-m-d', strtotime($data['date']));
            $ledger['currency']         = $data['currency'];

            //Delete ledger entries to 0 or null accounts
            if ( empty($ledger['financial_account_code']) )
            {
                unset($this->txn['ledgers'][$ledgers_index]);
            }
        }
        unset($ledger);

        //Return the array of txns
        //print_r($this->txn); exit;

        return true;

    }

}
