<?php

namespace App\Models;

use CodeIgniter\Model;

class FtDokter extends Model
{
    protected $DBGroup    = 'sqlsrv';
    protected $table      = 'FtDokter';
    protected $primaryKey = 'KdDoc';
    
    protected $allowedFields = [
        'KdDoc', 
        'NmDoc', 
        'Kategori',
        'Spesialis',
        'Address',
        'City',
        'KdPos',
        'Phone',
        'NmPoli',
        'KdDPJP',
        'KdDocMapping',
        'KdDocSatuSehat',
        'NIKKTP',
    ];

    public function searchDokter($keyword, $limit = 20, $offset = 0)
    {
        $builder = $this->builder();
        
        $builder->select('KdDoc');
        $builder->select('NmDoc');
        $builder->select('Kategori');
        $builder->select('Spesialis');
        $builder->select('Address');
        $builder->select('City');
        $builder->select('KdPos');
        $builder->select('Phone');
        $builder->select('NmPoli');
        $builder->select('KdDPJP');
        $builder->select('KdDocMapping');
        $builder->select('KdDocSatuSehat');
        $builder->select('NIKKTP');
        
        if (!empty($keyword)) {
            $builder->groupStart()
                ->like('NmDoc', $keyword)
                ->orLike('Kategori', $keyword)
                ->orLike('Spesialis', $keyword)
                ->orLike('NmPoli', $keyword)
            ->groupEnd();
        }

        $builder->limit($limit, $offset);
        $builder->orderBy('NmDoc', 'ASC');
        
        return $builder->get()->getResultArray();
    }

    public function countSearchDokter($keyword)
    {
        $builder = $this->builder();
        
        if (!empty($keyword)) {
            $builder->groupStart()
                ->like('NmDoc', $keyword)
                ->orLike('Kategori', $keyword)
                ->orLike('Spesialis', $keyword)
                ->orLike('NmPoli', $keyword)
            ->groupEnd();
        }
        
        return $builder->countAllResults();
    }

    public function updateIHSByNik($nik, $kddoc, $ihs)
    {
        return $this->builder()
                    ->where('NIKKTP', $nik)
                    ->where('KdDoc', $kddoc)
                    ->update(['KdDocSatuSehat' => $ihs]);
    }

}
