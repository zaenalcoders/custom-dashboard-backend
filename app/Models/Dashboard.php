<?php

namespace App\Models;


class Dashboard extends BaseModel
{

    /**
     * appends
     *
     * @var array
     */
    protected $appends = [
        'loading'
    ];

    /**
     * casts
     *
     * @var array
     */
    protected $casts = [
        'config' => 'array',
    ];
    
    /**
     * getLoadingAttribute
     *
     * @return void
     */
    public function getLoadingAttribute()
    {
        return true;
    }

    /**
     * data_source
     *
     * @return void
     */
    public function source()
    {
        return $this->belongsTo(DataSource::class, 'data_source_id', 'id');
    }
}
