<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;

class User extends BaseModel implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;

    /**
     * with
     *
     * @var array
     */
    protected $with = [
        'role'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var string[]
     */
    protected $hidden = [
        'password',
    ];

    /**
     * appends
     *
     * @var array
     */
    protected $appends = [
        'initials',
        'is_superadmin',
        'wa_number'
    ];

    /**
     * getInitialsAttribute
     *
     * @return string
     */
    public function getInitialsAttribute()
    {
        preg_match_all('/(?<=\b)\w/iu', $this->name, $matches);
        return mb_strtoupper(implode('', array_slice($matches[0], 0, 3)));
    }

    /**
     * getIsSuperadminAttribute
     *
     * @return boolean
     */
    public function getIsSuperadminAttribute()
    {
        $access = (array) ($this->role->access ?? []);
        return count($access) && $access[0] == '*';
    }

    /**
     * getPhoneNumberAttribute
     *
     * @return string
     */
    public function getWaNumberAttribute()
    {
        if (!$this->phone) {
            return null;
        }
        $phone = str_replace('-', '', $this->phone);
        $phone = str_replace(' ', '', $phone);
        if (Str::startsWith($phone, '+62') || Str::startsWith($phone, '62')) {
            return str_replace('+', '', $phone);
        } elseif (Str::startsWith($phone, '08')) {
            return '62' . substr($phone, 1, 20);
        } elseif (Str::startsWith($phone, '02')) {
            return '62' . substr($phone, 1, 20);
        } else {
            return '62' . str_replace('+', '', $phone);
        }
    }

    /**
     * role
     *
     * @return Illuminate\Database\Eloquent\Concerns\HasRelationships::belongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /**
     * getJWTIdentifier
     *
     * @return Illuminate\Database\Eloquent\Model::getKey
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
