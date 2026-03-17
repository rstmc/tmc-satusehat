<?php

namespace App\Models;

use CodeIgniter\Model;

class POLItpp extends Model
{
    protected $DBGroup    = 'sqlsrv';
    protected $table      = 'POLItpp';
    protected $primaryKey = 'KDPoli';
    
    protected $allowedFields = [
        'KDPoli', 
        'NMPoli', 
        'KdBPJS',
        'IdRuanganKemenkes',
        'NmRuanganKemenkes',
        'CodeRuanganKemenkes'
    ];

    public function searchPoli($keyword, $limit = 20, $offset = 0)
    {
        $builder = $this->builder();
        
        $builder->select('KDPoli');
        $builder->select('NMPoli');
        $builder->select('KdBPJS');
        $builder->select('IdRuanganKemenkes');
        $builder->select('NmRuanganKemenkes');
        $builder->select('CodeRuanganKemenkes');
        
        if (!empty($keyword)) {
            $builder->groupStart()
                ->like('NMPoli', $keyword)
            ->groupEnd();
        }

        $builder->limit($limit, $offset);
        $builder->orderBy('NMPoli', 'ASC');
        
        return $builder->get()->getResultArray();
    }

    public function countSearchPoli($keyword)
    {
        $builder = $this->builder();
        
        if (!empty($keyword)) {
            $builder->groupStart()
                    ->like('NMPoli', $keyword)
            ->groupEnd();
        }
        
        return $builder->countAllResults();
    }

}
