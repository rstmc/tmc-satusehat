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
        $sql = $this->_encounterBaseSql("CAST(A.Regdate AS DATE) = ? AND C.KdDocSatuSehat IS NOT NULL");

        return $this->db->query($sql, [$date, $date])->getResultArray();
    }

    /**
     * Dapatkan data encounter untuk satu regno tertentu.
     */
    public function getEncounterDataByRegno($regno)
    {
        $sql = $this->_encounterBaseSql("A.Regno = ?");

        return $this->db->query($sql, [$regno, $regno])->getResultArray();
    }

    /**
     * Dapatkan data record lengkap berdasarkan regno.
     */
    public function getDataByRegno($regno)
    {
        $data = $this->getEncounterDataByRegno($regno);
        return !empty($data) ? $data[0] : null;
    }

    /**
     * Base SQL yang dipakai bersama oleh getEncounterData dan getEncounterDataByRegno.
     */
    private function _encounterBaseSql(string $where): string
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
                      MAX(A.KdTuju) AS KdTuju,
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
                      MAX(G.reaksi_alergi) AS ReaksiAlergi,
                      NULL AS TglPulang,
                      NULL AS JamPulang
                  FROM Register A
                  INNER JOIN MasterPS B
                      ON B.MedRec COLLATE DATABASE_DEFAULT
                         = A.Medrec COLLATE DATABASE_DEFAULT
                         AND A.KdTuju = 'RJ'
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
                     AND G.rn = 1
                  WHERE {$where}
                  GROUP BY A.Regno

                  UNION ALL

                  SELECT
                      A.Regno,
                      MAX(A.Medrec) AS Medrec,
                      MAX(CAST(A.Firstname AS NVARCHAR(MAX))) AS Firstname,
                      NULL AS EcounterSatuSehat,
                      MAX(A.kdtuju) AS KdTuju,
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
                      MAX(CAST(A.Diagnosa AS NVARCHAR(MAX))) AS NmIcd,
                      NULL AS Subjective,
                      NULL AS Objective,
                      NULL AS Assessment,
                      NULL AS Planning,
                      '25064002' AS SnomedCodeKeluhanUtama,
                      'Feeling unwell' AS SnomedDisplayKeluhanUtama,
                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN NULL
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.td AS VARCHAR(50)) + '/' + CAST(AN.td_diastol AS VARCHAR(50))
                          ELSE CAST(DWS.tvit2 AS VARCHAR(50)) + '/' + CAST(DWS.tvit1_diastol AS VARCHAR(50))
                      END) AS Sistole,
                      NULL AS Diastole,
                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.suhu AS VARCHAR(50))
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.sh AS VARCHAR(50))
                          ELSE CAST(DWS.tvit11 AS VARCHAR(50))
                      END) AS Suhu,
                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.respi AS VARCHAR(50))
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.nfs AS VARCHAR(50))
                          ELSE CAST(DWS.tvit8 AS VARCHAR(50))
                      END) AS Pernapasan,
                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN NULL
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.spo2 AS VARCHAR(50))
                          ELSE CAST(DWS.tvit14 AS VARCHAR(50))
                      END) AS SpO2,
                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.fdj AS VARCHAR(50))
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.nd AS VARCHAR(50))
                          ELSE CAST(DWS.tvit5 AS VARCHAR(50))
                      END) AS Nadi,
                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.pb1 AS VARCHAR(50))
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.tbanak AS VARCHAR(50))
                          ELSE CAST(DWS.kg6 AS VARCHAR(50))
                      END) AS TinggiBadan,
                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.bb AS VARCHAR(50))
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.bbanak AS VARCHAR(50))
                          ELSE CAST(DWS.kg4 AS VARCHAR(50))
                      END) AS BeratBadan,
                      NULL AS RiwayatAlergi,
                      NULL AS RiwayatAlergiOpsi,
                      NULL AS ReaksiAlergi,
                      MAX(H.Tanggal) AS TglPulang,
                      MAX(H.Jam) AS JamPulang
                  FROM FPPRI A
                  INNER JOIN MasterPS B
                      ON B.MedRec COLLATE DATABASE_DEFAULT
                         = A.Medrec COLLATE DATABASE_DEFAULT
                         AND A.kdtuju = 'RI'
                  INNER JOIN FtDokter C
                      ON C.KdDoc COLLATE DATABASE_DEFAULT
                         = A.KdDocRS COLLATE DATABASE_DEFAULT
                  INNER JOIN POLItpp D
                      ON D.KDPoli COLLATE DATABASE_DEFAULT
                         = A.KdPoli COLLATE DATABASE_DEFAULT
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_assesment_medis_awal_neonatus
                      WHERE DATEDIFF(day, B.Bod, A.Regdate) <= 18
                        AND Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) NEO
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_assesment_medis_awal_anak
                      WHERE DATEDIFF(day, B.Bod, A.Regdate) > 18 
                        AND DATEDIFF(day, B.Bod, A.Regdate) <= 6570
                        AND Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) AN
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_assesment_medis_awal_dewasa
                      WHERE DATEDIFF(day, B.Bod, A.Regdate) > 6570
                        AND Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) DWS
                  LEFT JOIN FPulang H
                      ON H.Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                  WHERE {$where}
                  GROUP BY A.Regno";
    }

    public function updateEncounter($regno, $medrec, $encounterId)
    {
        $updated = $this->db->table('Register')
            ->where('Regno', $regno)
            ->where('Medrec', $medrec)
            ->update(['EcounterSatuSehat' => $encounterId]);

        try {
            $this->db->table('FPPRI')
                ->where('Regno', $regno)
                ->where('Medrec', $medrec)
                ->update(['EcounterSatuSehat' => $encounterId]);
        } catch (\Exception $e) {
            // Abaikan jika FPPRI tidak memiliki kolom EcounterSatuSehat
        }

        return $updated;
    }
}
