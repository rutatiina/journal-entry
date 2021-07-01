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

        $TxnEdit = new TxnEdit();
        $txnAttributes = $TxnEdit->run($id);

        $data = [
            'pageTitle' => 'Edit Journal Entry', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/financial-accounts/sales/estimates/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];

        if (FacadesRequest::wantsJson())
        {
            return $data;
        }
    }

    public function update(Request $request)
    {
        //print_r($request->all()); exit;

        $TxnStore = new TxnUpdate();
        $TxnStore->txnInsertData = $request->all();
        $insert = $TxnStore->run();

        if ($insert == false)
        {
            return [
                'status' => false,
                'messages' => $TxnStore->errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Journal Entry updated'],
            'number' => 0,
            'callback' => URL::route('accounting.sales.estimates.show', [$insert->id], false)
        ];
    }

    public function destroy($id)
    {
        $delete = Transaction::delete($id);

        if ($delete)
        {
            return [
                'status' => true,
                'message' => 'Journal Entry deleted',
            ];
        }
        else
        {
            return [
                'status' => false,
                'message' => implode('<br>', array_values(Transaction::$rg_errors))
            ];
        }
    }

    #-----------------------------------------------------------------------------------


    public function approve($id)
    {
        $TxnApprove = new TxnApprove();
        $approve = $TxnApprove->run($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => $TxnApprove->errors
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

    public function datatables(Request $request)
    {
        //return $request;

        $txns = Transaction::setRoute('show', route('accounting.sales.estimates.show', '_id_'))
            ->setRoute('edit', route('accounting.sales.estimates.edit', '_id_'))
            ->setRoute('process', route('accounting.sales.estimates.process', '_id_'))
            ->setSortBy($request->sort_by)
            ->paginate(false);

        return Datatables::of($txns)->make(true);
    }

    public function process($id, $processTo)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txn = Transaction::transaction($id); //print_r($originalTxn); exit;

        if ($txn == false)
        {
            return redirect()->back()->withErrors(['error' => 'Error #E001: Transaction not found']);
        }

        $txnAttributes = Transaction::transactionForEdit($id);

        //check if transaction has been processed before

        $txnAttributes['id'] = '';
        $txnAttributes['reference'] = $txn->number;
        $txnAttributes['internal_ref'] = $txn->id;
        $txnAttributes['due_date'] = $txn->expiry_date;

        //var_dump($processTo); exit;

        switch ($processTo)
        {

            case 'retainer-invoices':

                $txnAttributes['number'] = Transaction::entreeNextNumber('retainer_invoice');
                return [
                    'pageTitle' => 'Process Journal Entry into Retainer Invoice', #required
                    'pageAction' => 'Process Journal Entry', #required
                    'txnUrlStore' => '/retainer-invoices', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            case 'sales-orders':

                $txnAttributes['number'] = Transaction::entreeNextNumber('sales_order');
                return [
                    'pageTitle' => 'Process Journal Entry into Sales Order', #required
                    'pageAction' => 'Process Journal Entry', #required
                    'txnUrlStore' => '/sales-orders', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            case 'invoice':

                $txnAttributes['number'] = Transaction::entreeNextNumber('invoice');
                return [
                    'pageTitle' => 'Process Journal Entry into Invoice', #required
                    'pageAction' => 'Process Journal Entry', #required
                    'txnUrlStore' => '/invoice', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            case 'recurring-invoices':

                $txnAttributes['isRecurring'] = true;
                $txnAttributes['number'] = Transaction::entreeNextNumber('recurring_invoice');
                return [
                    'pageTitle' => 'Process Journal Entry into Recurring Invoice', #required
                    'pageAction' => 'Process Journal Entry', #required
                    'txnUrlStore' => '/recurring-invoices', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            default:

                break;

        }


        return redirect()->back()->withErrors(['error' => 'Unexpected Error #10015']);

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
