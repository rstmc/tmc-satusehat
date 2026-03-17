<?php

namespace App\Models;

use CodeIgniter\Model;

class DokterFotoModel extends Model
{
    protected $DBGroup = 'oracle';
    protected $table      = 'DOKTER_FOTO';
    protected $primaryKey = 'ID_DOKTER';

    protected $allowedFields = [
        'ID_DOKTER',
        'URL'
    ];

    protected $returnType     = 'array';
    protected $useTimestamps = false;
}
