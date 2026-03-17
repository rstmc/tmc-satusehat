<?php

namespace App\Models;

use CodeIgniter\Model;

class MasterObat extends Model
{
    protected $DBGroup    = 'sqlsrv';
    protected $table      = 'MasterObat';
    protected $primaryKey = 'KdObat';
    
    protected $allowedFields = [
        'KdObat',
        'NmObat',
        'MGroup',
        'MJenis',
        'MType',
        'Kategori',
        'Kemasan',
        'Satuan',
        'Komposisi',
        'Indikasi',
        'KdPrincipal',
        'SMinimum',
        'ValidUser',
        'IsiBox',
        'discount',
        'KdAcc',
        'izinedar',
        'expdate',
        'retriksi',
        'obatkronis',
        'kdtuju',
        'kdkategori',
        'antibiotik',
        'obat_be',
        'NmAlias',
        'KdBPJS',
        'SatuanKecil',
        'KdObatPRB',
        'IsiSatuanKecil',
        'ProtokolTerapi',
        'KelasTerapiObatKode',
        'KdBarcode',
        'ATC',
        'DDD',
        'unit',
        'DDDGram',
        'Kekuatan',
        'TBLRutePemberianId',
        'ObatTertentu',
        'ObatPrekursor',
        'ZatAktifObat',
        'KekuatanObat',
        'BentukObat',
        'BentukSediaan',
        'KekuatanSediaan',
        'JenisKemasan',
        'IsiKemasan'
    ];

    public function searchObat($keyword, $limit = 20, $offset = 0)
    {
        return $this->table($this->table)
                    ->like('NmObat', $keyword)
                    ->orLike('KdObat', $keyword)
                    ->orderBy('NmObat', 'ASC')
                    ->limit($limit, $offset)
                    ->get()
                    ->getResultArray();
    }

    public function countSearchObat($keyword)
    {
        return $this->table($this->table)
                    ->like('NmObat', $keyword)
                    ->orLike('KdObat', $keyword)
                    ->countAllResults();
    }
}
