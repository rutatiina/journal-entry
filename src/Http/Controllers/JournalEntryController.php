<?php

namespace Rutatiina\JournalEntry\Http\Controllers;

use Rutatiina\JournalEntry\Models\Setting;
use URL;
use PDF;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Rutatiina\JournalEntry\Models\JournalEntry;
use Rutatiina\FinancialAccounting\Traits\FinancialAccountingTrait;
use Rutatiina\Item\Traits\ItemsVueSearchSelect;
use Yajra\DataTables\Facades\DataTables;

use Rutatiina\JournalEntry\Classes\Store as TxnStore;
use Rutatiina\JournalEntry\Classes\Update as TxnUpdate;
use Rutatiina\JournalEntry\Classes\Approve as TxnApprove;
use Rutatiina\JournalEntry\Classes\Read as TxnRead;
use Rutatiina\JournalEntry\Classes\Copy as TxnCopy;
use Rutatiina\JournalEntry\Classes\Edit as TxnEdit;
use Rutatiina\JournalEntry\Classes\Number as TxnNumber;
use Rutatiina\JournalEntry\Traits\Recording;

class JournalEntryController extends Controller
{
    use FinancialAccountingTrait;
    use ItemsVueSearchSelect;
    use Recording; // >> get the item attributes template << !!important

    private  $txnEntreeSlug = 'Journal entries';

    public function __construct()
    {
		$this->middleware('permission:estimates.view');
		$this->middleware('permission:estimates.create', ['only' => ['create','store']]);
		$this->middleware('permission:estimates.update', ['only' => ['edit','update']]);
		$this->middleware('permission:estimates.delete', ['only' => ['destroy']]);
	}

    public function index()
	{
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
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
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new JournalEntry)->rgGetAttributes();

        $txnAttributes['status'] = 'approved';
        //$txnAttributes['contact_id'] = null;
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');

        $txnAttributes['currency'] = $tenant->base_currency;
        $txnAttributes['taxes'] = json_decode('{}');
        $txnAttributes['isRecurring'] = false;
        $txnAttributes['recurring'] = [
            'date_range' => [],
            'day_of_month' => '*',
            'month' => '*',
            'day_of_week' => '*',
        ];
        $txnAttributes['contact_notes'] = null;
        $txnAttributes['terms_and_conditions'] = null;
        $txnAttributes['recordings'] = [$this->recordingCreate()];

        return [
            'pageTitle' => 'Create Journal Entry', #required
            'pageAction' => 'Create', #required
            'txnUrlStore' => '/journal-entries', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function store(Request $request)
	{
	    //print_r($request->all()); exit;
        //return $request->all();

        $data = $request->all();

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

        $request->validate($rules);

	    $TxnStore = new TxnStore();
        $TxnStore->txnInsertData = $request->all();
        $insert = $TxnStore->run();

        if ($insert == false) {
            return [
                'status'    => false,
                'messages'  => $TxnStore->errors
            ];
        }

        return [
            'status'    => true,
            'messages'  => ['Journal Entry saved'],
            'number'    => 0,
            'callback'  => URL::route('journal-entries.show', [$insert->id], false)
        ];

    }

    public function show($id)
	{
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        if (FacadesRequest::wantsJson()) {
            $TxnRead = new TxnRead();
            return $TxnRead->run($id);
        }
    }

    public function edit($id)
	{
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $TxnEdit = new TxnEdit();
        $txnAttributes = $TxnEdit->run($id);

        $data = [
            'pageTitle' => 'Edit Journal Entry', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/financial-accounts/sales/estimates/'.$id, #required
            'txnAttributes' => $txnAttributes, #required
        ];

        if (FacadesRequest::wantsJson()) {
            return $data;
        }
    }

    public function update(Request $request)
	{
        //print_r($request->all()); exit;

        $TxnStore = new TxnUpdate();
        $TxnStore->txnEntreeSlug = $this->txnEntreeSlug;
        $TxnStore->txnInsertData = $request->all();
        $insert = $TxnStore->run();

        if ($insert == false) {
            return [
                'status'    => false,
                'messages'  => $TxnStore->errors
            ];
        }

        return [
            'status'    => true,
            'messages'  => ['Journal Entry updated'],
            'number'    => 0,
            'callback'  => URL::route('accounting.sales.estimates.show', [$insert->id], false)
        ];
	}

    public function destroy($id)
	{
		$delete = Transaction::delete($id);

		if ($delete) {
			return [
				'status' => true,
				'message' => 'Journal Entry deleted',
			];
		} else {
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

        if ($approve == false) {
            return [
                'status'    => false,
                'messages'   => $TxnApprove->errors
            ];
        }

        return [
            'status'    => true,
            'messages'   => ['Journal entry approved'],
        ];

    }

    public function copy($id)
	{
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $TxnCopy = new TxnCopy();
        $txnAttributes = $TxnCopy->run($id);

        $TxnNumber = new TxnNumber();
        $txnAttributes['number'] = $TxnNumber->run($this->txnEntreeSlug);

        $data = [
            'pageTitle' => 'Copy Journal Entry', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/financial-accounts/sales/estimates', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        if (FacadesRequest::wantsJson()) {
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
			->paginate(false)
			->findByEntree($this->txnEntreeSlug);

        return Datatables::of($txns)->make(true);
    }

    public function process($id, $processTo)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txn = Transaction::transaction($id); //print_r($originalTxn); exit;

        if ($txn == false) {
            return redirect()->back()->withErrors(['error' => 'Error #E001: Transaction not found']);
        }

        $txnAttributes = Transaction::transactionForEdit($id);

        //check if transaction has been processed before

        $txnAttributes['id'] = '';
        $txnAttributes['reference'] = $txn->number;
        $txnAttributes['internal_ref'] = $txn->id;
        $txnAttributes['due_date'] = $txn->expiry_date;

        //var_dump($processTo); exit;

        switch ($processTo) {

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

        foreach (array_reverse($request->ids) as $id) {
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
            'maccounts-estimates-export-'.date('Y-m-d-H-m-s').'.xlsx',
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
        return $pdf->inline($txn->type->name.'-'.$txn->number.'.pdf');
        //return $pdf->download($txn->type->name.'-'.$txn->number.'.pdf');
    }
}
