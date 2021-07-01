<?php

namespace Rutatiina\JournalEntry\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Rutatiina\JournalEntry\Models\JournalEntry;
use Rutatiina\FinancialAccounting\Traits\FinancialAccountingTrait;
use Rutatiina\Item\Traits\ItemsVueSearchSelect;
use Rutatiina\JournalEntry\Services\JournalEntryService;
use Yajra\DataTables\Facades\DataTables;

use Rutatiina\JournalEntry\Classes\Update as TxnUpdate;
use Rutatiina\JournalEntry\Classes\Approve as TxnApprove;
use Rutatiina\JournalEntry\Classes\Read as TxnRead;
use Rutatiina\JournalEntry\Classes\Copy as TxnCopy;
use Rutatiina\JournalEntry\Classes\Edit as TxnEdit;

class JournalEntryController extends Controller
{
    use FinancialAccountingTrait;
    use ItemsVueSearchSelect;

    // >> get the item attributes template << !!important

    public function __construct()
    {
        //$this->middleware('permission:estimates.view');
        //$this->middleware('permission:estimates.create', ['only' => ['create','store']]);
        //$this->middleware('permission:estimates.update', ['only' => ['edit','update']]);
        //$this->middleware('permission:estimates.delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txns = JournalEntry::latest()->paginate();

        return [
            'tableData' => $txns
        ];
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new JournalEntry)->rgGetAttributes();

        $txnAttributes['status'] = 'approved';
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');

        $txnAttributes['currency'] = $tenant->base_currency;
        $txnAttributes['notes'] = null;
        $txnAttributes['recordings'] = [
            [
                'selectedTaxes' => [], #required
                'selectedItem' => json_decode('{}'), #required
                'displayTotal' => 0,
                'description' => '',
                'debit' => 0,
                'credit' => 0,
                'contact_id' => '',
                'financial_account_code' => '',
            ]
        ];

        return [
            'pageTitle' => 'Create Journal Entry', #required
            'pageAction' => 'Create', #required
            'txnUrlStore' => '/journal-entries', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function store(Request $request)
    {
        //return $request->all();

        $storeService = JournalEntryService::store($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => JournalEntryService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Journal Entry saved'],
            'callback' => route('journal-entries.show', [$storeService->id], false)
        ];

    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txn = JournalEntry::findOrFail($id);
        $txn->load('contact', 'recordings.financial_account', 'ledgers');
        $txn->setAppends([
            'total_in_words',
        ]);

        return $txn->toArray();
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txnAttributes = JournalEntryService::edit($id);

        return [
            'pageTitle' => 'Edit Journal Entry', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/journal-entries/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function update(Request $request)
    {
        //print_r($request->all()); exit;

        $storeService = JournalEntryService::update($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => JournalEntryService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Journal Entry updated'],
            'callback' => route('journal-entries.show', [$storeService->id], false)
        ];
    }

    public function destroy($id)
    {
        $destroy = JournalEntryService::destroy($id);

        if ($destroy)
        {
            return [
                'status' => true,
                'messages' => ['Journal Entry deleted'],
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => JournalEntryService::$errors
            ];
        }
    }

    #-----------------------------------------------------------------------------------


    public function approve($id)
    {
        $approve = JournalEntryService::approve($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => JournalEntryService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Journal entry approved'],
        ];

    }

    public function copy($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $TxnCopy = new TxnCopy();
        $txnAttributes = $TxnCopy->run($id);

        $data = [
            'pageTitle' => 'Copy Journal Entry', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/financial-accounts/sales/estimates', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        if (FacadesRequest::wantsJson())
        {
            return $data;
        }
    }

    public function exportToExcel(Request $request)
    {
        $txns = collect([]);

        $txns->push([
            'DATE',
            'DOCUMENT#',
            'REFERENCE',
            'CUSTOMER',
            'STATUS',
            'EXPIRY DATE',
            'TOTAL',
            ' ', //Currency
        ]);

        foreach (array_reverse($request->ids) as $id)
        {
            $txn = Transaction::transaction($id);

            $txns->push([
                $txn->date,
                $txn->number,
                $txn->reference,
                $txn->contact_name,
                $txn->status,
                $txn->expiry_date,
                $txn->total,
                $txn->base_currency,
            ]);
        }

        $export = $txns->downloadExcel(
            'maccounts-estimates-export-' . date('Y-m-d-H-m-s') . '.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

    public function pdf($id)
    {
        ini_set('max_execution_time', 300); //300 seconds = 5 minutes
        set_time_limit(300);

        $txn = Transaction::transaction($id);

        $data = [
            'tenant' => Auth::user()->tenant,
            'txn' => $txn
        ];

        //return view('limitless-bs4::sales.estimates.pdf')->with($data);

        $pdf = PDF::loadView('limitless-bs4::sales.estimates.pdf', $data);
        return $pdf->inline($txn->type->name . '-' . $txn->number . '.pdf');
        //return $pdf->download($txn->type->name.'-'.$txn->number.'.pdf');
    }
}
