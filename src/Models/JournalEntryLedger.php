<?php

namespace Rutatiina\JournalEntry\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Scopes\TenantIdScope;

class JournalEntryLedger extends Model
{
    use LogsActivity;

    protected static $logName = 'JournalEntryLedger';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_journal_entry_ledgers';

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

    public function journal_entry()
    {
        return $this->belongsTo('Rutatiina\JournalEntry\Models\JournalEntry', 'journal_entry_id');
    }

}
