<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('journal-entries')->group(function () {

        Route::get('query', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController@query');
        //Route::get('summary', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController@summary');
        Route::post('export-to-excel', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController@approve');
        //Route::post('contact-estimates', 'Rutatiina\JournalEntry\Http\Controllers\Sales\ReceiptController@estimates');
        Route::get('{id}/copy', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController@copy');

        Route::post('{txnId}/process', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController@process');
        Route::get('{txnId}/process/{processTo}', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController@process');

    });

    Route::resource('journal-entries/settings', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntrySettingsController');
    Route::resource('journal-entries', 'Rutatiina\JournalEntry\Http\Controllers\JournalEntryController');

});
