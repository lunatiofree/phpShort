<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Domain
 *
 * @mixin Builder
 * @package App
 */
class Pixel extends Model
{
    /**
     * @param Builder $query
     * @param $value
     * @return Builder
     */
    public function scopeSearchName(Builder $query, $value)
    {
        return $query->where('name', 'like', '%' . $value . '%');
    }

    /**
     * @param Builder $query
     * @param $value
     * @return Builder
     */
    public function scopeOfType(Builder $query, $value)
    {
        return $query->where('type', '=', $value);
    }

    /**
     * @param Builder $query
     * @param $value
     * @return Builder
     */
    public function scopeOfUser(Builder $query, $value)
    {
        return $query->where('user_id', '=', $value);
    }

    /**
     * Get the total links count under the pixel.
     *
     * @return int
     */
    public function getTotalLinksAttribute()
    {
        return $this->belongsToMany('App\Models\Link')->count();
    }

    /**
     * Get the user that owns the pixel.
     *
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * Get the links under the pixel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function links() {
        return $this->belongsToMany('App\Models\Link');
    }
}
