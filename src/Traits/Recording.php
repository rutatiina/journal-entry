<?php

namespace Rutatiina\JournalEntry\Traits;

trait Recording
{
    public function __construct()
    {}

    public function recordingCreate()
    {
        return [
            'selectedTaxes' => [], #required
            'selectedItem' => json_decode('{}'), #required
            'displayTotal' => 0,
            'description' => '',
            'debit' => 0,
            'credit' => 0,
            'contact_id' => '',
            'financial_account_code' => '',
        ];

    }

}
