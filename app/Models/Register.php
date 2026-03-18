<?php

namespace App\Models;

use CodeIgniter\Model;

class Register extends Model
{
    protected $DBGroup    = 'sqlsrv';
    protected $table      = 'Register';
    protected $primaryKey = 'Regno';
    protected $allowedFields = ['EcounterSatuSehat'];

    /**
     * Dapatkan data encounter untuk semua pasien berdasarkan tanggal registrasi.
     */
    public function getEncounterData($date)
    {
        $sql = $this->_encounterBaseSql() . "
                 WHERE
                     CAST(A.Regdate AS DATE) = ?
                     AND C.KdDocSatuSehat IS NOT NULL
                 GROUP BY
                     A.Regno";

        return $this->db->query($sql, [$date])->getResultArray();
    }

    /**
     * Dapatkan data encounter untuk satu regno tertentu.
     */
    public function getEncounterDataByRegno($regno)
    {
        $sql = $this->_encounterBaseSql() . "
                 WHERE
                     A.Regno = ?
                     AND C.KdDocSatuSehat IS NOT NULL
                 GROUP BY
                     A.Regno";

        return $this->db->query($sql, [$regno])->getResultArray();
    }

    /**
     * Base SQL yang dipakai bersama oleh getEncounterData dan getEncounterDataByRegno.
     */
    private function _encounterBaseSql(): string
    {
        return "WITH PengkajianTerakhir AS (
                     SELECT
                         *,
                         ROW_NUMBER() OVER (
                             PARTITION BY Regno
                             ORDER BY created_at DESC
                         ) AS rn
                     FROM dbFORM.dbo.mp_pengkajian_rawat_jalan
                 )

                 SELECT
                     A.Regno,
                     MAX(A.Medrec) AS Medrec,
                     MAX(CAST(A.Firstname AS NVARCHAR(MAX))) AS Firstname,
                     MAX(A.EcounterSatuSehat) AS EcounterSatuSehat,
                     MAX(B.IHSSatuSehat) AS IHSSatuSehat,
                     MAX(B.NoIden) AS NoIden,
                     MAX(A.Regdate) AS Regdate,
                     MAX(A.RegTime) AS RegTime,
                     MAX(C.NmDoc) AS NmDoc,
                     MAX(C.KdDocSatuSehat) AS KdDocSatuSehat,
                     MAX(A.KdPoli) AS KdPoli,
                     MAX(D.NmRuanganKemenkes) AS NmRuanganKemenkes,
                     MAX(D.IdRuanganKemenkes) AS IdRuanganKemenkes,
                     MAX(A.KdIcd) AS KdIcd,
                     MAX(CAST(E.DIAGNOSA AS NVARCHAR(MAX))) AS NmIcd,
                     MAX(CAST(F.Subjective AS NVARCHAR(MAX))) AS Subjective,
                     MAX(CAST(F.Objective AS NVARCHAR(MAX))) AS Objective,
                     MAX(CAST(F.Assessment AS NVARCHAR(MAX))) AS Assessment,
                     MAX(CAST(F.Planning AS NVARCHAR(MAX))) AS Planning,
                     '25064002' AS SnomedCodeKeluhanUtama,
                     'Feeling unwell' AS SnomedDisplayKeluhanUtama,
                     MAX(G.sistol_text) AS Sistole,
                     MAX(G.diastol_text) AS Diastole,
                     MAX(G.suhu) AS Suhu,
                     MAX(G.pernafasan) AS Pernapasan,
                     MAX(G.saturasi_oxygen) AS SpO2,
                     MAX(G.nadi) AS Nadi,
                     MAX(G.tinggi_badan) AS TinggiBadan,
                     MAX(G.berat_badan) AS BeratBadan,
                     MAX(G.riwayat_alergi) AS RiwayatAlergi,
                     MAX(G.riwayat_alergi_opsi) AS RiwayatAlergiOpsi,
                     MAX(G.reaksi_alergi) AS ReaksiAlergi
                 FROM Register A
                 INNER JOIN MasterPS B
                     ON B.MedRec COLLATE DATABASE_DEFAULT
                        = A.Medrec COLLATE DATABASE_DEFAULT
                 INNER JOIN FtDokter C
                     ON C.KdDoc COLLATE DATABASE_DEFAULT
                        = A.KdDoc COLLATE DATABASE_DEFAULT
                 INNER JOIN POLItpp D
                     ON D.KDPoli COLLATE DATABASE_DEFAULT
                        = A.KdPoli COLLATE DATABASE_DEFAULT
                 LEFT JOIN TBLICD10 E
                     ON E.KdIcd COLLATE DATABASE_DEFAULT
                        = A.KdIcd COLLATE DATABASE_DEFAULT
                 LEFT JOIN dbERM.dbo.cppt F
                     ON F.Regno COLLATE DATABASE_DEFAULT
                        = A.Regno COLLATE DATABASE_DEFAULT
                    AND F.MedRec COLLATE DATABASE_DEFAULT
                        = A.Medrec COLLATE DATABASE_DEFAULT
                    AND F.KdDoc COLLATE DATABASE_DEFAULT
                        = A.KdDoc COLLATE DATABASE_DEFAULT
                    AND F.DiisiOleh = 'dokter'
                 LEFT JOIN PengkajianTerakhir G
                     ON G.Regno COLLATE DATABASE_DEFAULT
                        = A.Regno COLLATE DATABASE_DEFAULT
                    AND G.rn = 1";
    }

    public function updateEncounter($regno, $medrec, $encounterId)
    {
        return $this->db->table($this->table)
            ->where('Regno', $regno)
            ->where('Medrec', $medrec)
            ->update(['EcounterSatuSehat' => $encounterId]);
    }
}
