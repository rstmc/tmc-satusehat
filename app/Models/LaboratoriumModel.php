<?php

namespace App\Models;

use CodeIgniter\Model;

class LaboratoriumModel extends Model
{
    protected $DBGroup    = 'sqlsrv';
    protected $table      = 'HeadBilLab'; // Default table, though we use queries
    protected $primaryKey = 'NoTran';

    /**
     * Get Lab Orders from HeadBilLabTEMP
     * @param string $regno
     * @return array
     */
    public function getLabOrders($regno)
    {
        // HeadBilLabTEMP seems to be the order table
        $sql = "SELECT
        H.NoTran,
        D.NmTarif,
        F.KdDocSatuSehat AS KdDocSatuSehatLab,
        F.NmDoc AS NmDocLab,
        HH.Catatan AS CatatanHasil
        FROM
        HeadBilLab H
        INNER JOIN DetailBilLab D ON H.NoTran = D.NoTran
        INNER JOIN FtDokter F ON F.KdDoc = H.KdDokter
        INNER JOIN HeadHasil HH ON HH.Notran = H.NoTran
        WHERE H.Regno = ?";
        return $this->db->query($sql, [$regno])->getResultArray();
    }

    /**
     * Get Lab Results joining HeadBilLab and DetailBilLab
     * @param string $regno
     * @return array
     */
    public function getLabResults($regno)
    {
        // Join HeadBilLab and DetailBilLab
        // Selecting distinct columns or all. 
        // Note: Both tables might have same column names (e.g. Regno, Tanggal).
        // We might need to alias or be careful.
        $sql = "SELECT 
                    H.NoTran as HeaderNoTran,
                    H.NoLab,
                    H.TglHasil,
                    H.JamHasil,
                    H.TglSampel,
                    H.AsalSampel,
                    H.Kesan,
                    H.Saran,
                    H.KdDoc as HeaderKdDoc,
                    H.NmDoc as HeaderNmDoc,
                    D.* 
                FROM HeadBilLab H
                INNER JOIN DetailBilLab D ON H.NoTran = D.NoTran
                WHERE H.Regno = ?";
        return $this->db->query($sql, [$regno])->getResultArray();
    }
    
    /**
     * Get just the header for DiagnosticReport
     */
    public function getLabHeaders($regno)
    {
        $sql = "SELECT * FROM HeadBilLab WHERE Regno = ?";
        return $this->db->query($sql, [$regno])->getResultArray();
    }

    /**
     * Get details for a specific transaction
     */
    public function getLabDetails($noTran)
    {
        $sql = "SELECT * FROM DetailBilLab WHERE NoTran = ?";
        return $this->db->query($sql, [$noTran])->getResultArray();
    }
}
