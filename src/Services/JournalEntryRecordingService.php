<?php

namespace Rutatiina\JournalEntry\Services;

use Rutatiina\JournalEntry\Models\JournalEntryRecording;

class JournalEntryRecordingService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        //print_r($data['items']); exit;

        //Save the items >> $data['items']
        foreach ($data['items'] as &$item)
        {
            $item['journal_entry_id'] = $data['id'];

            $itemTaxes = (is_array($item['taxes'])) ? $item['taxes'] : [] ;
            unset($item['taxes']);

            $itemModel = JournalEntryRecording::create($item);

        }
        unset($item);

    }

}
