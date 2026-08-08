<?php

namespace App\Models;

use CodeIgniter\Model;

class PsriM extends Model
{
    protected $DBGroup = 'oracle';
    
    public function getAllPsri($tanggal)
    {
        $sql = "
        SELECT 
            0 AS NOM,
            A.*,
            CASE WHEN JK = 0 THEN 'LAKI-LAKI' ELSE 'PEREMPUAN' END AS JENIS_KELAMIN,
            NAMA_DOKTER,
            A.ID_UNIT,
            NAMA_RUANG,
            ID_KAMAR,
            TO_CHAR(B.TGL_REG, 'DD/MM/YYYY HH24:MI:SS') AS TGL_REG,
            B.NO_REG,
            B.PENANGGUNG_JAWAB,
            B.UMUR,
            B.ID_JAMIN,
            B.NAMA_PENJAMIN,
            C.NAMA_UNIT AS KLSTMC,
            C.ID_POLI AS KLSBPJS,
            COALESCE(JML,0) AS JML,KETERANGAN
        FROM
        (
            SELECT 
                A.TGL_REG,
                A.NO_REG,
                A.ID_RM,
                B.NAMA_PASIEN,
                D.ID_UNIT,
                D.NAMA_UNIT AS NAMA_RUANG,
                E.NAMA_DOKTER,
                A.ID_KAMAR,
                A.PENANGGUNG_JAWAB,
                A.UMUR_TAHUN || A.UMUR_BULAN || A.UMUR_HARI AS UMUR,
                A.ID_JAMIN,
                F.NAMA_PENJAMIN
            FROM USER_TMC.REGISTRASI A
            LEFT JOIN USER_TMC.PASIEN B ON B.ID_RM = A.ID_RM
            LEFT JOIN USER_TMC.KAMAR C ON C.ID_KAMAR = A.ID_KAMAR
            LEFT JOIN USER_TMC.UNIT_PELAYANAN D ON D.ID_UNIT = C.ID_UNIT 
            LEFT JOIN USER_TMC.DOKTER E ON A.ID_DOKTER = E.ID_DOKTER
            LEFT JOIN USER_TMC.PENJAMIN F 
                ON A.JENIS_PENJAMIN = F.ID_PENJAMIN 
                AND A.ID_JAMIN = F.ID_JAMIN
            WHERE 
                A.ID_UNIT = '100'
                AND TRUNC(A.TGL_REG) <= TRUNC(TO_DATE(?, 'YYYY-MM-DD'))
                AND A.TGL_END IS NULL 
                AND A.KELUAR = 0
        ) B
        LEFT JOIN USER_TMC.PASIEN A ON A.ID_RM = B.ID_RM
        LEFT JOIN USER_TMC.UNIT_PELAYANAN C ON A.ID_KELASHAK = C.ID_UNIT
        LEFT JOIN (
            SELECT 
    NO_REG,
    COUNT(A.ID_DIET) AS JML,
    LISTAGG(NAMA_DIET, ',') WITHIN GROUP (ORDER BY A.ID_DIET) AS KETERANGAN
    FROM DIET_PASIEN A LEFT JOIN MASTER_DIET B ON A.ID_DIET=B.ID_DIET
    GROUP BY NO_REG
    ORDER BY NO_REG
        ) D ON B.NO_REG = D.NO_REG
        ";

        return $this->db->query($sql, [$tanggal])->getResult();
    }

    public function getPsri($noreg)
    {
        $sql = "
        SELECT 
            0 AS NOM,
            A.*,
            CASE WHEN JK = 0 THEN 'LAKI-LAKI' ELSE 'PEREMPUAN' END AS JENIS_KELAMIN,
            NAMA_DOKTER,
            A.ID_UNIT,
            NAMA_RUANG,
            ID_KAMAR,
            TO_CHAR(B.TGL_REG, 'DD/MM/YYYY HH24:MI:SS') AS TGL_REG,
            B.NO_REG,
            B.PENANGGUNG_JAWAB,
            B.UMUR,
            B.ID_JAMIN,
            B.NAMA_PENJAMIN,
            C.NAMA_UNIT AS KLSTMC,
            C.ID_POLI AS KLSBPJS,
            COALESCE(JML,0) AS JML,KETERANGAN
        FROM
        (
            SELECT 
                A.TGL_REG,
                A.NO_REG,
                A.ID_RM,
                B.NAMA_PASIEN,
                D.ID_UNIT,
                D.NAMA_UNIT AS NAMA_RUANG,
                E.NAMA_DOKTER,
                A.ID_KAMAR,
                A.PENANGGUNG_JAWAB,
                A.UMUR_TAHUN || A.UMUR_BULAN || A.UMUR_HARI AS UMUR,
                A.ID_JAMIN,
                F.NAMA_PENJAMIN
            FROM USER_TMC.REGISTRASI A
            LEFT JOIN USER_TMC.PASIEN B ON B.ID_RM = A.ID_RM
            LEFT JOIN USER_TMC.KAMAR C ON C.ID_KAMAR = A.ID_KAMAR
            LEFT JOIN USER_TMC.UNIT_PELAYANAN D ON D.ID_UNIT = C.ID_UNIT 
            LEFT JOIN USER_TMC.DOKTER E ON A.ID_DOKTER = E.ID_DOKTER
            LEFT JOIN USER_TMC.PENJAMIN F 
                ON A.JENIS_PENJAMIN = F.ID_PENJAMIN 
                AND A.ID_JAMIN = F.ID_JAMIN
            WHERE 
                A.ID_UNIT = '100'
                AND A.NO_REG = ?
                AND A.TGL_END IS NULL 
                AND A.KELUAR = 0
        ) B
        LEFT JOIN USER_TMC.PASIEN A ON A.ID_RM = B.ID_RM
        LEFT JOIN USER_TMC.UNIT_PELAYANAN C ON A.ID_KELASHAK = C.ID_UNIT
        LEFT JOIN (
            SELECT 
    NO_REG,
    COUNT(A.ID_DIET) AS JML,
    LISTAGG(NAMA_DIET, ',') WITHIN GROUP (ORDER BY A.ID_DIET) AS KETERANGAN
    FROM DIET_PASIEN A LEFT JOIN MASTER_DIET B ON A.ID_DIET=B.ID_DIET
    GROUP BY NO_REG
    ORDER BY NO_REG
        ) D ON B.NO_REG = D.NO_REG
        ";

        return $this->db->query($sql, [$noreg])->getResult();
    }
}
