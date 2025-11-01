<?php

namespace App\Models;


class Dashboard extends BaseModel
{
    /**
     * casts
     *
     * @var array
     */
    protected $casts = [
        'config' => 'array',
    ];

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
