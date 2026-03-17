<?php

namespace App\Models;

use CodeIgniter\Model;

class MasterPS extends Model
{
    protected $DBGroup    = 'sqlsrv';
    protected $table      = 'MasterPS';
    protected $primaryKey = 'Medrec';
    
    protected $allowedFields = [
        'Medrec', 
        'Firstname', 
        'NoIden',
        'Address',
        'City',
        'Propinsi',
        'Kecamatan',
        'Kelurahan',
        'Phone',
        'KdSex',
        'IHSSatuSehat'
    ];

    public function searchPasien($patient = '', $rm = '', $ihs = '', $limit = 20, $offset = 0)
    {
        $builder = $this->builder();
        
        $builder->select('Medrec');
        $builder->select('Firstname');
        $builder->select('NoIden');
        $builder->select('Address');
        $builder->select('City');
        $builder->select('Propinsi');
        $builder->select('Kecamatan');
        $builder->select('Kelurahan');
        $builder->select('Phone');
        $builder->select('KdSex');
        $builder->select('IHSSatuSehat');
        $builder->select("CASE 
            WHEN KdSex = 'L' THEN 'Laki-laki' 
            WHEN KdSex = 'P' THEN 'Perempuan' 
            ELSE KdSex 
        END AS SexName");
        // $builder->where('LEN(NoIden) > 14');
        // $builder->where('IHSSatuSehat IS NULL');
        
        if (!empty($patient)) {
            $builder->groupStart()
                ->like('Firstname', $patient)
            ->groupEnd();
        }
        if (!empty($rm)) {
            $builder->groupStart()
                ->like('Medrec', $rm)
            ->groupEnd();
        }
        if (!empty($ihs)) {
            $builder->groupStart()
                ->like('IHSSatuSehat', $ihs)
            ->groupEnd();
        }

        $builder->limit($limit, $offset);
        $builder->orderBy('Firstname', 'ASC');
        
        return $builder->get()->getResultArray();
    }

    public function countSearchPasien($patient = '', $rm = '', $ihs = '')
    {
        $builder = $this->builder();
        
        if (!empty($patient)) {
            $builder->groupStart()
                ->like('Firstname', $patient)
            ->groupEnd();
        }
        if (!empty($rm)) {
            $builder->groupStart()
                ->like('Medrec', $rm)
            ->groupEnd();
        }
        if (!empty($ihs)) {
            $builder->groupStart()
                ->like('IHSSatuSehat', $ihs)
            ->groupEnd();
        }
        
        return $builder->countAllResults();
    }

    public function updateIHSByNik($nik, $medrec, $ihs)
    {
        return $this->builder()
                    ->where('NoIden', $nik)
                    ->where('Medrec', $medrec)
                    ->update(['IHSSatuSehat' => $ihs]);
    }
}
