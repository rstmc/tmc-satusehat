<?php

namespace App\Models;

use CodeIgniter\Model;

class SatuSehatLogModel extends Model
{
    protected $DBGroup    = 'sqlsrv';
    protected $table            = 'satu_sehat_resources_id';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'Regno', 
        'resourceType', 
        'resourceSubType', 
        'resourceID', 
        'created_at', 
        'updated_at'
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
}
