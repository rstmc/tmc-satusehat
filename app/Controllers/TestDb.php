<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;

class TestDb extends Controller
{
    public function oracle()
    {
        $db = Database::connect('oracle');

        $query = $db->query("SELECT SYSDATE AS NOW FROM DUAL");
        $row   = $query->getRowArray();

        return $this->response->setJSON([
            'db'   => 'oracle',
            'data' => $row
        ]);
    }

    public function sqlserver()
    {
        $db = Database::connect('sqlsrv');

        $query = $db->query("SELECT GETDATE() AS NOW");
        $row   = $query->getRowArray();

        return $this->response->setJSON([
            'db'   => 'sqlserver',
            'data' => $row
        ]);
    }
}
