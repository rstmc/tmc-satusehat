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
                DetailApotikTmp.ObatPulang,
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
            ->where('HeadApotikTmp.Regno', $regno)
            ->get()
            ->getResultArray();
    }
    public function getDispenseObatByRegno($regno)
    {
        // Try HeadApotik first
        $data = $this->db->table('HeadApotik')
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
                MasterObat.Satuan
            ')
            ->join('DetailApotik', 'HeadApotik.BLCode = DetailApotik.BLCode')
            ->join('MasterObat', 'DetailApotik.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotik.Regno', $regno)
            ->get()
            ->getResultArray();

        if (!empty($data)) {
            return $data;
        }

        // If not found, try HeadApotikKronis
        return $this->db->table('HeadApotikKronis')
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
                MasterObat.Satuan
            ')
            ->join('DetailApotikKronis', 'HeadApotikKronis.BLCode = DetailApotikKronis.BLCode')
            ->join('MasterObat', 'DetailApotikKronis.KodeObat = MasterObat.KdObat', 'left')
            ->where('HeadApotikKronis.Regno', $regno)
            ->get()
            ->getResultArray();
    }
}
