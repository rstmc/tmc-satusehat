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
        return "SELECT
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
                      NULL AS IdBedKemenkes,
                      NULL AS NmBedKemenkes,
                      MAX(A.KdIcd) AS KdIcd,
                      MAX(CAST(E.DIAGNOSA AS NVARCHAR(MAX))) AS NmIcd,
                      MAX(CAST(F.Subjective AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT) AS Subjective,
                      MAX(COALESCE(
                          NULLIF(CAST(F.Objective AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(
                              SUBSTRING(
                                  CONCAT(
                                      CASE WHEN NULLIF(LTRIM(RTRIM(CAST(G_TRI.klhutm AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)), '') IS NOT NULL THEN ', ' + LTRIM(RTRIM(CAST(G_TRI.klhutm AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)) ELSE '' END,
                                      CASE WHEN NULLIF(LTRIM(RTRIM(CAST(G_TRI.anamnesis AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)), '') IS NOT NULL THEN ', ' + LTRIM(RTRIM(CAST(G_TRI.anamnesis AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)) ELSE '' END,
                                      CASE WHEN NULLIF(LTRIM(RTRIM(CAST(G_TRI.diagnosis AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)), '') IS NOT NULL THEN ', ' + LTRIM(RTRIM(CAST(G_TRI.diagnosis AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)) ELSE '' END
                                  ),
                                  3,
                                  4000
                              ),
                              ''
                          )
                      ) COLLATE DATABASE_DEFAULT) AS Objective,
                      MAX(CAST(F.Assessment AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT) AS Assessment,
                      MAX(COALESCE(
                          NULLIF(CAST(F.Planning AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(
                              SUBSTRING(
                                  CONCAT(
                                      CASE WHEN NULLIF(LTRIM(RTRIM(CAST(G_TRI.tm_ket1 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)), '') IS NOT NULL THEN ', ' + LTRIM(RTRIM(CAST(G_TRI.tm_ket1 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)) ELSE '' END,
                                      CASE WHEN NULLIF(LTRIM(RTRIM(CAST(G_TRI.tm_ket2 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)), '') IS NOT NULL THEN ', ' + LTRIM(RTRIM(CAST(G_TRI.tm_ket2 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)) ELSE '' END,
                                      CASE WHEN NULLIF(LTRIM(RTRIM(CAST(G_TRI.tm_ket3 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)), '') IS NOT NULL THEN ', ' + LTRIM(RTRIM(CAST(G_TRI.tm_ket3 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)) ELSE '' END,
                                      CASE WHEN NULLIF(LTRIM(RTRIM(CAST(G_TRI.tm_ket4 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)), '') IS NOT NULL THEN ', ' + LTRIM(RTRIM(CAST(G_TRI.tm_ket4 AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT)) ELSE '' END
                                  ),
                                  3,
                                  4000
                              ),
                              ''
                          )
                      ) COLLATE DATABASE_DEFAULT) AS Planning,
                      '25064002' AS SnomedCodeKeluhanUtama,
                      'Feeling unwell' AS SnomedDisplayKeluhanUtama,

                      -- ── Vital Signs & Asesmen Rawat Jalan / IGD ──
                      MAX(COALESCE(
                          NULLIF(CAST(G.sistol_text AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.td1 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.td1 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.td1 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.tdsistol_akhir AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.tdsistol AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Sistole,

                      MAX(COALESCE(
                          NULLIF(CAST(G.diastol_text AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.diastole AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.td2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.td2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.tddiastol_akhir AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.tddiastol AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Diastole,

                      MAX(COALESCE(
                          NULLIF(CAST(G.suhu AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.sh AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.sh AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.sh AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.suhu_akhir AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.suhu AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Suhu,

                      MAX(COALESCE(
                          NULLIF(CAST(G.pernafasan AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.nfs AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.nfs AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.nfs AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.fknafas_akhir AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.fknafas AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Pernapasan,

                      MAX(COALESCE(
                          NULLIF(CAST(G.saturasi_oxygen AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.spo2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.spo2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.spo2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.sp_akhir AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.sp AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS SpO2,

                      MAX(COALESCE(
                          NULLIF(CAST(G.nadi AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.nd AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.nd AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.nd AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.fknadi_akhir AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.fknadi AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Nadi,

                      MAX(COALESCE(
                          NULLIF(CAST(G.tinggi_badan AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.tgi AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.tb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.tb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.tb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS TinggiBadan,

                      MAX(COALESCE(
                          NULLIF(CAST(G.berat_badan AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_ANAK.bb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_GIGI.bb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_MAT.bb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.bb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS BeratBadan,

                      MAX(CAST(G.riwayat_alergi AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT) AS RiwayatAlergi,
                      MAX(CAST(G.riwayat_alergi_opsi AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT) AS RiwayatAlergiOpsi,
                      MAX(COALESCE(
                          NULLIF(CAST(G.reaksi_alergi AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT, ''),
                          NULLIF(CAST(G_TRI.alrglain AS NVARCHAR(MAX)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS ReaksiAlergi,

                      NULL AS TglPulang,
                      NULL AS JamPulang
                  FROM Register A
                  INNER JOIN MasterPS B
                      ON B.MedRec COLLATE DATABASE_DEFAULT
                         = A.Medrec COLLATE DATABASE_DEFAULT
                         AND A.KdTuju IN ('RJ', '2')
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
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_pengkajian_rawat_jalan
                      WHERE Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) G
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_pengkajian_rawat_jalan_anak
                      WHERE Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) G_ANAK
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_pengkajian_rawat_jalan_gigi
                      WHERE Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) G_GIGI
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_pengkajian_rawat_jalan_maternitas
                      WHERE Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) G_MAT
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_triase_sekunder
                      WHERE Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) G_TRI
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
                      MAX(KLS.NmRuanganKemenkes) AS NmRuanganKemenkes,
                      MAX(KLS.IdRuanganKemenkes) AS IdRuanganKemenkes,
                      MAX(DTT.IdRuanganKemenkes) AS IdBedKemenkes,
                      MAX(DTT.NmRuanganKemenkes) AS NmBedKemenkes,
                      MAX(A.KdIcd) AS KdIcd,
                      MAX(CAST(A.Diagnosa AS NVARCHAR(MAX))) AS NmIcd,
                      NULL AS Subjective,
                      NULL AS Objective,
                      NULL AS Assessment,
                      NULL AS Planning,
                      '25064002' AS SnomedCodeKeluhanUtama,
                      'Feeling unwell' AS SnomedDisplayKeluhanUtama,

                      -- ── Vital Signs Rawat Inap (Medis + Catatan Keperawatan) ──
                      MAX(COALESCE(
                          CASE 
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN NULL
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.td AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE CAST(DWS.tvit2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          END,
                          CASE 
                              WHEN CK_RANAP.tensi LIKE '%/%' THEN CAST(LEFT(CK_RANAP.tensi, CHARINDEX('/', CK_RANAP.tensi) - 1) AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE NULLIF(CAST(CK_RANAP.tensi AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                          END
                      ) COLLATE DATABASE_DEFAULT) AS Sistole,

                      MAX(COALESCE(
                          CASE 
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN NULL
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.td_diastol AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE CAST(DWS.tvit1_diastol AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          END,
                          CASE 
                              WHEN CK_RANAP.tensi LIKE '%/%' THEN CAST(SUBSTRING(CK_RANAP.tensi, CHARINDEX('/', CK_RANAP.tensi) + 1, 50) AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE NULL
                          END
                      ) COLLATE DATABASE_DEFAULT) AS Diastole,

                      MAX(COALESCE(
                          CASE 
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.suhu AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.sh AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE CAST(DWS.tvit11 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          END,
                          NULLIF(CAST(CK_RANAP.suhu AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Suhu,

                      MAX(COALESCE(
                          CASE 
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.respi AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.nfs AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE CAST(DWS.tvit8 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          END,
                          NULLIF(CAST(CK_RANAP.pernapasan AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Pernapasan,

                      MAX(COALESCE(
                          CASE 
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN NULL
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.spo2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE CAST(DWS.tvit14 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          END,
                          NULLIF(CAST(CK_RANAP.spo2 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS SpO2,

                      MAX(COALESCE(
                          CASE 
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.fdj AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.nd AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                              ELSE CAST(DWS.tvit5 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          END,
                          NULLIF(CAST(CK_RANAP.nadi AS VARCHAR(50)) COLLATE DATABASE_DEFAULT, '')
                      ) COLLATE DATABASE_DEFAULT) AS Nadi,

                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.pb1 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.tbanak AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          ELSE CAST(DWS.kg6 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                      END) AS TinggiBadan,

                      MAX(CASE 
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= 18 THEN CAST(NEO.bb AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          WHEN DATEDIFF(day, B.Bod, A.Regdate) <= (365 * 18) THEN CAST(AN.bbanak AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                          ELSE CAST(DWS.kg4 AS VARCHAR(50)) COLLATE DATABASE_DEFAULT
                      END) AS BeratBadan,

                      NULL AS RiwayatAlergi,
                      NULL AS RiwayatAlergiOpsi,
                      NULL AS ReaksiAlergi,
                      MAX(H.Tanggal) AS TglPulang,
                      MAX(H.Jam) AS JamPulang
                  FROM FPPRI A

                  INNER JOIN MasterPS B
                      ON B.MedRec COLLATE DATABASE_DEFAULT = A.Medrec COLLATE DATABASE_DEFAULT

                  INNER JOIN FtDokter C
                      ON C.KdDoc COLLATE DATABASE_DEFAULT
                         = A.KdDocRawat COLLATE DATABASE_DEFAULT
                  LEFT JOIN Detailttidur DTT
                      ON DTT.kdbangsal COLLATE DATABASE_DEFAULT = A.KdBangsal COLLATE DATABASE_DEFAULT
                      AND DTT.kdkelas COLLATE DATABASE_DEFAULT = A.KdKelas COLLATE DATABASE_DEFAULT
                      AND DTT.nokamar COLLATE DATABASE_DEFAULT = A.nokamar COLLATE DATABASE_DEFAULT
                      AND DTT.ttnomor COLLATE DATABASE_DEFAULT = A.NoTTidur COLLATE DATABASE_DEFAULT
                  LEFT JOIN TBLKelas KLS
                      ON KLS.KDKelas COLLATE DATABASE_DEFAULT = A.KdKelas COLLATE DATABASE_DEFAULT
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
                  OUTER APPLY (
                      SELECT TOP 1 * FROM dbFORM.dbo.mp_catatan_keperawatan
                      WHERE Regno COLLATE DATABASE_DEFAULT = A.Regno COLLATE DATABASE_DEFAULT
                      ORDER BY id DESC
                  ) CK_RANAP
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
