<?php

namespace App\Models;


class Role extends BaseModel
{
    /**
     * casts
     *
     * @var array
     */
    protected $casts = [
        'access' => 'object',
        'dashboard' => 'object',
    ];

    /**
     * appends
     *
     * @var array
     */
    protected $appends = [
        'dashboard_access'
    ];

    /**
     * getDashboardAttribute
     *
     * @return void
     */
    public function getDashboardAccessAttribute()
    {
        return (count($this->access ?? [])
            ? ($this->access[0] == '*'
                ? [
                    'purchase_order' => true,
                    'work_order' => true,
                    'task' => true,
                    'document' => true,
                    'esar' => true,
                    'invoice' => true,
                    'expense' => true,
                    'esar_status' => true
                ]
                : ($this->dashboard ?? json_decode('{}')))
            : json_decode('{}'));
    }

    /**
     * users
     *
     * @return Illuminate\Database\Eloquent\Concerns\HasRelationships::hasMany
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
