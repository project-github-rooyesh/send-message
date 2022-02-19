<?php
namespace Esmaili\Message\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'user_id',
        'mobile',
        'message',
        'token',
        'type'
    ];

}
