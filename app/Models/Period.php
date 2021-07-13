<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    protected $fillable = ['period_id', 'start', 'end'];

    protected $primaryKey = 'period_id';

}