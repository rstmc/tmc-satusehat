<?php

namespace App\Models;

use CodeIgniter\Model;

class ApotekModel extends Model
{
    protected $DBGroup = 'sqlsrv';
    protected $table = 'HeadApotikTmp';
    protected $primaryKey = 'Id';

    public function getObatByRegno($regno)
    {
        return $this->db->table('HeadApotikTmp')
            ->select('
                HeadApotikTmp.Id AS HeadId,
                HeadApotikTmp.Regno,
                HeadApotikTmp.RegDate,
                HeadApotikTmp.MedRec,
                HeadApotikTmp.KdDoc,
                HeadApotikTmp.NoResep,
                DetailApotikTmp.Id AS DetailId,
                DetailApotikTmp.KodeObat,
                DetailApotikTmp.NamaObat,
                DetailApotikTmp.Qty,
                DetailApotikTmp.AturanPakai,
                DetailApotikTmp.KeteranganPakai,
                DetailApotikTmp.Signa1,
                DetailApotikTmp.Signa2,
                DetailApotikTmp.JumlahHari,
                DetailApotikTmp.NoteSigna,
                DetailApotikTmp.NoteCaraMinumObat,
                DetailApotikTmp.ObatKronis,
                DetailApotikTmp.SignaManual,
                HeadApotikTmp.ObatPulang,
                DetailApotikTmp.SignaTiming,
                DetailApotikTmp.NoRacikan,
                DetailApotikTmp.BentukRacikan,
                COALESCE(MasterObat.KfaCode, MasterObat.kfa_code) as KFA,
                COALESCE(MasterObat.KfaCode, MasterObat.kfa_code) as KFA_Ingredient,
                MasterObat.ZatAktifObat,
                MasterObat.Kekuatan,
                MasterObat.Satuan,
                MasterObat.Kemasan,
                MasterObat.BentukSediaan,
                MasterObat.Medication_id_satu_sehat
            ')
            ->join('DetailApotikTmp', 'HeadApotikTmp.Id = DetailApotikTmp.IdHead')
            ->join('MasterObat', 'DetailApotikTmp.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotikTmp.Regno', $regno)
            ->get()
            ->getResultArray();
    }
    public function getDispenseObatByRegno($regno)
    {
        // Try HeadApotik first
        $dataHead = $this->db->table('HeadApotik')
            ->select('
                HeadApotik.BLCode,
                HeadApotik.Regno,
                HeadApotik.RegDate,
                HeadApotik.MedRec,
                HeadApotik.Firstname,
                HeadApotik.KdDoc,
                HeadApotik.NoResep,
                HeadApotik.TglResep,
                HeadApotik.ValidUser,
                DetailApotik.KodeObat,
                DetailApotik.NamaObat,
                DetailApotik.Qty,
                DetailApotik.AturanPakai,
                DetailApotik.KeteranganPakai,
                DetailApotik.Signa1,
                DetailApotik.Signa2,
                DetailApotik.JumlahHari,
                DetailApotik.NoteSigna,
                DetailApotik.NoteCaraMinumObat,
                COALESCE(MasterObat.KfaCode, MasterObat.kfa_code) as KFA,
                COALESCE(MasterObat.KfaCode, MasterObat.kfa_code) as KFA_Ingredient,
                MasterObat.Satuan,
                MasterObat.Medication_id_satu_sehat,
                0 AS IsObatKronis,
                ISNULL(HeadApotik.ObatPulang, 0) AS ObatPulang
            ')
            ->join('DetailApotik', 'HeadApotik.BLCode = DetailApotik.BLCode')
            ->join('MasterObat', 'DetailApotik.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotik.Regno', $regno)
            ->get()
            ->getResultArray();

        // Try HeadApotikKronis
        $dataKronis = $this->db->table('HeadApotikKronis')
            ->select('
                HeadApotikKronis.BLCode,
                HeadApotikKronis.Regno,
                HeadApotikKronis.RegDate,
                HeadApotikKronis.MedRec,
                HeadApotikKronis.Firstname,
                HeadApotikKronis.KdDoc,
                HeadApotikKronis.NoResep,
                HeadApotikKronis.TglResep,
                HeadApotikKronis.ValidUser,
                DetailApotikKronis.KodeObat,
                DetailApotikKronis.NamaObat,
                DetailApotikKronis.Qty,
                DetailApotikKronis.AturanPakai,
                DetailApotikKronis.KeteranganPakai,
                DetailApotikKronis.Signa1,
                DetailApotikKronis.Signa2,
                DetailApotikKronis.JumlahHari,
                DetailApotikKronis.NoteSigna,
                DetailApotikKronis.NoteCaraMinumObat,
                COALESCE(MasterObat.KfaCode, MasterObat.kfa_code) as KFA,
                COALESCE(MasterObat.KfaCode, MasterObat.kfa_code) as KFA_Ingredient,
                MasterObat.Satuan,
                MasterObat.Medication_id_satu_sehat,
                1 AS IsObatKronis,
                0 AS ObatPulang
            ')
            ->join('DetailApotikKronis', 'HeadApotikKronis.BLCode = DetailApotikKronis.BLCode')
            ->join('MasterObat', 'DetailApotikKronis.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotikKronis.Regno', $regno)
            ->get()
            ->getResultArray();

        return array_merge($dataHead, $dataKronis);
    }
    public function getRiwayatObatByMedrec($medrec, $currentRegno = null, $currentRegdate = null)
    {
        if (empty($medrec)) {
            return [];
        }

        $builderTmp = $this->db->table('HeadApotikTmp')
            ->select('
                HeadApotikTmp.Id AS HeadId,
                HeadApotikTmp.Regno,
                HeadApotikTmp.RegDate,
                HeadApotikTmp.MedRec,
                HeadApotikTmp.KdDoc,
                HeadApotikTmp.NoResep,
                DetailApotikTmp.Id AS DetailId,
                DetailApotikTmp.KodeObat,
                DetailApotikTmp.NamaObat,
                DetailApotikTmp.Qty,
                DetailApotikTmp.AturanPakai,
                DetailApotikTmp.KeteranganPakai,
                DetailApotikTmp.Signa1,
                DetailApotikTmp.Signa2,
                DetailApotikTmp.JumlahHari,
                DetailApotikTmp.NoteSigna,
                DetailApotikTmp.NoteCaraMinumObat,
                DetailApotikTmp.ObatKronis,
                DetailApotikTmp.SignaManual,
                HeadApotikTmp.ObatPulang,
                DetailApotikTmp.SignaTiming,
                DetailApotikTmp.NoRacikan,
                DetailApotikTmp.BentukRacikan,
                \'91000330\' as KFA,
                \'91000330\' as KFA_Ingredient,
                MasterObat.ZatAktifObat,
                MasterObat.Kekuatan,
                MasterObat.Satuan,
                MasterObat.Kemasan,
                MasterObat.BentukSediaan
            ')
            ->join('DetailApotikTmp', 'HeadApotikTmp.Id = DetailApotikTmp.IdHead')
            ->join('MasterObat', 'DetailApotikTmp.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotikTmp.MedRec', $medrec);

        if (!empty($currentRegno)) {
            $builderTmp->where('HeadApotikTmp.Regno <>', $currentRegno);
        }

        if (!empty($currentRegdate)) {
            $builderTmp->where('HeadApotikTmp.RegDate <', $currentRegdate);
        }

        $data = $builderTmp
            ->orderBy('HeadApotikTmp.RegDate', 'DESC')
            ->get()
            ->getResultArray();

        if (!empty($data)) {
            return $data;
        }

        $builderHead = $this->db->table('HeadApotik')
            ->select('
                HeadApotik.BLCode,
                HeadApotik.Regno,
                HeadApotik.RegDate,
                HeadApotik.MedRec,
                HeadApotik.Firstname,
                HeadApotik.KdDoc,
                HeadApotik.NoResep,
                HeadApotik.TglResep,
                HeadApotik.ValidUser,
                DetailApotik.KodeObat,
                DetailApotik.NamaObat,
                DetailApotik.Qty,
                DetailApotik.AturanPakai,
                DetailApotik.KeteranganPakai,
                DetailApotik.Signa1,
                DetailApotik.Signa2,
                DetailApotik.JumlahHari,
                DetailApotik.NoteSigna,
                DetailApotik.NoteCaraMinumObat,
                \'91000330\' as KFA,
                \'91000330\' as KFA_Ingredient,
                MasterObat.Satuan,
                ISNULL(HeadApotik.ObatPulang, 0) AS ObatPulang
            ')
            ->join('DetailApotik', 'HeadApotik.BLCode = DetailApotik.BLCode')
            ->join('MasterObat', 'DetailApotik.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotik.MedRec', $medrec);

        if (!empty($currentRegno)) {
            $builderHead->where('HeadApotik.Regno <>', $currentRegno);
        }

        if (!empty($currentRegdate)) {
            $builderHead->where('HeadApotik.RegDate <', $currentRegdate);
        }

        $dataHead = $builderHead->get()->getResultArray();

        $builderKronis = $this->db->table('HeadApotikKronis')
            ->select('
                HeadApotikKronis.BLCode,
                HeadApotikKronis.Regno,
                HeadApotikKronis.RegDate,
                HeadApotikKronis.MedRec,
                HeadApotikKronis.Firstname,
                HeadApotikKronis.KdDoc,
                HeadApotikKronis.NoResep,
                HeadApotikKronis.TglResep,
                HeadApotikKronis.ValidUser,
                DetailApotikKronis.KodeObat,
                DetailApotikKronis.NamaObat,
                DetailApotikKronis.Qty,
                DetailApotikKronis.AturanPakai,
                DetailApotikKronis.KeteranganPakai,
                DetailApotikKronis.Signa1,
                DetailApotikKronis.Signa2,
                DetailApotikKronis.JumlahHari,
                DetailApotikKronis.NoteSigna,
                DetailApotikKronis.NoteCaraMinumObat,
                \'91000330\' as KFA,
                \'91000330\' as KFA_Ingredient,
                MasterObat.Satuan,
                0 AS ObatPulang
            ')
            ->join('DetailApotikKronis', 'HeadApotikKronis.BLCode = DetailApotikKronis.BLCode')
            ->join('MasterObat', 'DetailApotikKronis.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotikKronis.MedRec', $medrec);

        if (!empty($currentRegno)) {
            $builderKronis->where('HeadApotikKronis.Regno <>', $currentRegno);
        }

        if (!empty($currentRegdate)) {
            $builderKronis->where('HeadApotikKronis.RegDate <', $currentRegdate);
        }

        $dataKronis = $builderKronis->get()->getResultArray();

        $mergedData = array_merge($dataHead, $dataKronis);

        usort($mergedData, function ($a, $b) {
            return strcmp($b['RegDate'] ?? '', $a['RegDate'] ?? '');
        });

        return $mergedData;
    }
}
