<?php

namespace App\Models;


class DataColumn extends BaseModel
{
    /**
     * source
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function source()
    {
        return $this->belongsTo(DataSource::class);
    }
}
