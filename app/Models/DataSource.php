<?php

namespace App\Models;


class DataSource extends BaseModel
{
    /**
     * columns
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function columns()
    {
        return $this->hasMany(DataColumn::class);
    }
}
