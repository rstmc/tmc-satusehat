<?php

namespace App\Models;

use CodeIgniter\Model;

class LoginorclModel extends Model
{
    protected $DBGroup = 'oracle';

    /**
     * Validasi login user Oracle
     */
    public function login($namaUser, $passb)
    {
        $sql = "
            SELECT
                p.ID_USER,
                p.NAMA_USER,
                p.PASSB
            FROM PEMAKAI p
            WHERE p.NAMA_USER = ?
              AND p.PASSB = ?
              AND p.ID_USER IN (
                    SELECT ID_USER
                    FROM MASTER_SMENU
                    WHERE ID_UNIT = '405'
                    GROUP BY ID_USER
              )
        ";

        return $this->db
            ->query($sql, [$namaUser, $passb])
            ->getRowArray(); // login cukup 1 baris
    }
}
