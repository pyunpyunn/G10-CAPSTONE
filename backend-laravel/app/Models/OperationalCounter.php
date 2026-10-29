<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalCounter extends Model
{
    protected $table = 'operational_sequences';

    protected $primaryKey = 'sequence_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['sequence_key', 'next_value', 'sequence_prefix'];

    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }
}
