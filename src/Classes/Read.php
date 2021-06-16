<?php

namespace Rutatiina\JournalEntry\Classes;

use Rutatiina\JournalEntry\Models\JournalEntry;

class Read
{

    public function __construct()
    {}

    public function run($id)
    {
        $Txn = JournalEntry::find($id);

        if ($Txn) {
            //txn has been found so continue normally
        } else {
            $this->errors[] = 'Transaction not found';
            return false;
        }

        $Txn->load('recordings.contact', 'recordings.financial_account');

        $f = new \NumberFormatter( locale_get_default(), \NumberFormatter::SPELLOUT );
        $Txn->total_in_words = ucfirst($f->format($Txn->total));

        return $Txn->toArray();

    }

}
