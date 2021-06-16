<?php

namespace Rutatiina\JournalEntry\Traits;

use Rutatiina\FinancialAccounting\Models\Entree as ModelEntree;

trait Entree
{
    public function __construct()
    {}

    public function entree($idOrSlug)
    {
        if (is_numeric($idOrSlug)) {
            $txnEntree = ModelEntree::find($idOrSlug);
        } else {
            $txnEntree = ModelEntree::where('slug', $idOrSlug)->first();
        }

        if ($txnEntree) {
            //do nothing
        } else {
            return false;
        }

        $txnEntree->load('config', 'config.txn_type');

        return $txnEntree->toArray();

    }

}
