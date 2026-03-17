<?php

namespace App\Models;

use CodeIgniter\Model;

class RadiologiSqlsrvModel extends Model
{
    protected $DBGroup = 'sqlsrv';

    private function baseQuery()
    {
        return "
            FROM
            (
                SELECT a.Regno, a.NoTran, a.Regdate, a.Medrec, a.Firstname,
                       c.IHSSatuSehat, d.KdDoc, d.kdDocSatuSehat, d.NmDoc,a.Hasil,e.NmTarif,e.Kdtarif
                FROM HasilRadiologi a
                LEFT JOIN MasterPs c ON a.Medrec = c.Medrec
                LEFT JOIN FtDokter d ON a.KdDoc = d.KdDoc
                LEFT JOIN DetailBilRad e ON a.NoTran = e.NoTran
                WHERE a.KdDoc IS NOT NULL
                  AND c.IHSSatuSehat IS NOT NULL
            ) P
            INNER JOIN
            (
                SELECT x.Regno, x.Regdate, x.Medrec, x.EcounterSatuSehat,
                       y.KdDocSatuSehat AS KdDPJP,
                       y.NmDoc AS NmDPJP,
                       x.KdIcd,
                       w.DIAGNOSA
                FROM Register x
                LEFT JOIN FtDokter y ON x.KdDoc = y.KdDoc
                LEFT JOIN TBLICD10 w ON x.KdIcd = w.KDICD
                WHERE x.EcounterSatuSehat IS NOT NULL
                  AND x.KdIcd IS NOT NULL
            ) Q ON P.Regno = Q.Regno
        ";
    }

    public function getRadiologi($startDate, $endDate, $limit, $offset)
    {
        $where  = [];
        $params = [];

        if ($startDate && $endDate) {
            $where[] = "P.Regdate BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                P.*, Q.EcounterSatuSehat, Q.KdIcd, Q.DIAGNOSA,
                Q.KdDPJP, Q.NmDPJP
            {$this->baseQuery()}
            $whereSql
            ORDER BY P.Regdate ASC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ";

        $params[] = $offset;
        $params[] = $limit;

        return $this->db->query($sql, $params)->getResultArray();
    }

    public function countRadiologi($startDate, $endDate)
    {
        $where  = [];
        $params = [];

        if ($startDate && $endDate) {
            $where[] = "P.Regdate BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT COUNT(*) AS total
            {$this->baseQuery()}
            $whereSql
        ";

        return (int) $this->db->query($sql, $params)->getRow()->total;
    }
}
