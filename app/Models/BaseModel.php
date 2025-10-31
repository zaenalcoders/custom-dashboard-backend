<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BaseModel extends Model
{
    use HasUuids;

    /**
     * incrementing
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * keyType
     *
     * @var string
     */
    public $keyType = 'string';

    /**
     * dateFormat
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * hasCreatedByColumnCache
     *
     * @var array
     */
    protected static array $hasCreatedByColumnCache = [];

    /**
     * hasUpdatedByColumnCache
     *
     * @var array
     */
    protected static array $hasUpdatedByColumnCache = [];

    /**
     * boot
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $table = $model->getTable();
            if (!isset(self::$hasCreatedByColumnCache[$table])) {
                self::$hasCreatedByColumnCache[$table] = Schema::hasColumn($table, 'created_by');
            }
            if (auth()->hasUser() && self::$hasCreatedByColumnCache[$table]) {
                $model->created_by = auth()->user()->id;
            }
            if (!isset(self::$hasUpdatedByColumnCache[$table])) {
                self::$hasUpdatedByColumnCache[$table] = Schema::hasColumn($table, 'updated_by');
            }
            if (auth()->hasUser() && self::$hasUpdatedByColumnCache[$table]) {
                $model->updated_by = auth()->user()->id;
            }
        });

        static::updating(function ($model) {
            $table = $model->getTable();
            if (!isset(self::$hasUpdatedByColumnCache[$table])) {
                self::$hasUpdatedByColumnCache[$table] = Schema::hasColumn($table, 'updated_by');
            }
            if (auth()->hasUser() && self::$hasUpdatedByColumnCache[$table]) {
                $model->updated_by = auth()->user()->id;
            }
        });
    }

    /**
     * creator
     *
     * @return void
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id')
            ->withDefault(['name' => '-']);
    }

    /**
     * updater
     *
     * @return void
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id')
            ->withDefault(['name' => '-']);
    }
}
