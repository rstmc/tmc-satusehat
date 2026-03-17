<?php

namespace App\Models;

use CodeIgniter\Model;

class RadiologiOracleModel extends Model
{
    protected $DBGroup = 'oracle';
    protected $table  = 'RAD_DATA';
    protected $primaryKey = 'IDRAD';

    protected $allowedFields = [
        'REGNO',
        'NOTRAN',
        'REGDATE',
        'MEDREC',
        'FIRSTNAME',
        'IHSSATUSEHAT',
        'KDDOC',
        'KDDOCSATUSEHAT',
        'NMDOC',
        'ECOUNTERSATUSEHAT',
        'KDDPJP',
        'NMDPJP',
        'KDICD',
        'DIAGNOSA',
        'ASCN',
        'HASIL'
    ];

    protected $useTimestamps = false;
}
