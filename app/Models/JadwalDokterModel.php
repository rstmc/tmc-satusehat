<?php

namespace App\Models;

use CodeIgniter\Model;

class JadwalDokterModel extends Model
{
    protected $DBGroup = 'oracle';

    public function getJadwalDokter(string $tgl): array
    {
        $sql = "
            SELECT A.*, NAMA_UNIT, B.NAMA_DOKTER, B.KODEDOKTER, D.URL AS FOTO_URL
            FROM (
                SELECT
                    A.ID_UNIT,
                    A.ID_DOKTER,
                    A.TGL,
                    A.HADIR,
                    'PAGI' NAMA,
                    '1' NOUR,
                    A.H1,
                    TO_CHAR(A.JM1,'hh24:mi') JM,
                    TO_CHAR(A.JS1,'hh24:mi') JS,
                    JP1 JP
                FROM USER_TMC.JADWAL_DOKTER1 A
                WHERE (A.HADIR = 10 OR A.HADIR = 4)
                  AND A.TGL = TO_DATE(?, 'YYYY-MM-DD')
                  AND (A.H1 > 0 AND A.H1 <= 3)

                UNION ALL

                SELECT
                    A.ID_UNIT,
                    A.ID_DOKTER,
                    A.TGL,
                    A.HADIR,
                    'SIANG' NAMA,
                    '2' NOUR,
                    A.H2,
                    TO_CHAR(A.JM2,'hh24:mi') JM,
                    TO_CHAR(A.JS2,'hh24:mi') JS,
                    JP2 JP
                FROM USER_TMC.JADWAL_DOKTER1 A
                WHERE (A.HADIR = 10 OR A.HADIR = 4)
                  AND A.TGL = TO_DATE(?, 'YYYY-MM-DD')
                  AND (A.H2 > 0 AND A.H2 <= 3)

                UNION ALL

                SELECT
                    A.ID_UNIT,
                    A.ID_DOKTER,
                    A.TGL,
                    A.HADIR,
                    'SORE' NAMA,
                    '3' NOUR,
                    A.H3,
                    TO_CHAR(A.JM3,'hh24:mi') JM,
                    TO_CHAR(A.JS3,'hh24:mi') JS,
                    JP3 JP
                FROM USER_TMC.JADWAL_DOKTER1 A
                WHERE (A.HADIR = 10 OR A.HADIR = 4)
                  AND A.TGL = TO_DATE(?, 'YYYY-MM-DD')
                  AND (A.H3 > 0 AND A.H3 <= 3)
            ) A
            LEFT JOIN USER_TMC.DOKTER B ON B.ID_DOKTER = A.ID_DOKTER
            LEFT JOIN UNIT_PELAYANAN C ON A.ID_UNIT = C.ID_UNIT
            LEFT JOIN USER_TMC.DOKTER_FOTO D ON D.ID_DOKTER = A.ID_DOKTER
            ORDER BY A.ID_DOKTER, NOUR
        ";

        // bind TGL ke 3 UNION
        return $this->db->query($sql, [$tgl, $tgl, $tgl])->getResultArray();
    }
}
