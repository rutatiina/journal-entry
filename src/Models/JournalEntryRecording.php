<?php

namespace Rutatiina\JournalEntry\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Scopes\TenantIdScope;

class JournalEntryRecording extends Model
{
    use LogsActivity;

    protected static $logName = 'JournalEntryRecording';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_journal_entry_recordings';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantIdScope);
    }

    public function getTaxesAttribute($value)
    {
        $_array_ = json_decode($value);
        if (is_array($_array_)) {
            return $_array_;
        } else {
            return [];
        }
    }

    public function financial_account()
    {
        return $this->hasOne('Rutatiina\FinancialAccounting\Models\Account', 'code', 'financial_account_code');
    }

    public function journal_entry()
    {
        return $this->belongsTo('Rutatiina\JournalEntry\Models\JournalEntry', 'journal_entry_id');
    }

    public function contact()
    {
        return $this->hasOne('Rutatiina\Contact\Models\Contact', 'id', 'contact_id');
    }

}
