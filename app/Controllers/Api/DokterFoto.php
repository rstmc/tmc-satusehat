<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\DokterFotoModel;

class DokterFoto extends ResourceController
{
    protected $format = 'json';

    // ======================
    // UPLOAD FOTO
    // ======================
  
 public function create()
{
    // ===============================
    // Ambil input (JSON / FormData)
    // ===============================
    $rawInput = $this->request->getBody();
    $data     = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $idDokter   = $this->request->getPost('ID_DOKTER');
        $fotoBase64 = $this->request->getPost('foto');
    } else {
        $idDokter   = $data['ID_DOKTER'] ?? null;
        $fotoBase64 = $data['foto'] ?? null;
    }

    if (!$idDokter || !$fotoBase64) {
        return $this->fail('ID_DOKTER dan foto wajib diisi', 400);
    }

    // ===============================
    // Parse & validasi base64
    // ===============================
    if (!preg_match('/^data:image\/(\w+);base64,/', $fotoBase64, $type)) {
        return $this->fail('Format base64 tidak valid', 400);
    }

    $fotoBase64 = substr($fotoBase64, strpos($fotoBase64, ',') + 1);
    $imageType  = strtolower($type[1]);

    $allowed = ['jpeg', 'jpg', 'png', 'webp', 'gif'];
    if (!in_array($imageType, $allowed)) {
        return $this->fail('Format gambar tidak didukung', 400);
    }

    $imageData = base64_decode($fotoBase64, true);
    if ($imageData === false) {
        return $this->fail('Gagal decode base64', 400);
    }

    // ===============================
    // Persiapan folder & file
    // ===============================
    $uploadDir = ROOTPATH . 'public/uploads/dokter/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = ($imageType === 'jpeg') ? 'jpg' : $imageType;
    $newName   = 'dokter_' . time() . '.' . $extension;
    $savePath = $uploadDir . $newName;

    if (file_put_contents($savePath, $imageData) === false) {
        return $this->fail('Gagal menyimpan file', 500);
    }

    // ===============================
    // Database Oracle (TRANSACTION)
    // ===============================
    $db = \Config\Database::connect();

    try {
        $db->transStart();

        // ---- CEK FOTO LAMA
        $stmt = $db->query(
            'SELECT "URL" FROM DOKTER_FOTO WHERE "ID_DOKTER" = ?',
            [$idDokter]
        );

        $fotoLama = $stmt ? $stmt->getRowArray() : null;

        // ---- HAPUS FOTO LAMA
        if ($fotoLama) {
            $oldFile = $uploadDir . $fotoLama['URL'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }

            $db->query(
                'DELETE FROM DOKTER_FOTO WHERE "ID_DOKTER" = ?',
                [$idDokter]
            );
        }

        // ---- INSERT FOTO BARU
        $db->query(
            'INSERT INTO DOKTER_FOTO ("ID_DOKTER", "URL") VALUES (?, ?)',
            [$idDokter, $newName]
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            @unlink($savePath);
            return $this->fail('Gagal menyimpan data ke database', 500);
        }

    } catch (\Throwable $e) {
        $db->transRollback();
        @unlink($savePath);

        log_message('error', 'Upload foto dokter error: ' . $e->getMessage());

        return $this->fail('Database error', 500);
    }

    // ===============================
    // Response sukses
    // ===============================
    return $this->respondCreated([
        'status'  => true,
        'message' => 'Foto berhasil diupload',
        'data'    => [
            'ID_DOKTER' => $idDokter,
            'URL'       => base_url('uploads/dokter/' . $newName),
            'filename'  => $newName,
            'size'      => filesize($savePath)
        ]
    ]);
}


    // ======================
    // DELETE FOTO
    // ======================
public function delete($id = null)
{
    if (!$id) {
        return $this->fail('ID dokter harus diberikan');
    }

    $model = new DokterFotoModel();

    // Cari foto dokter
    $foto = $model->builder()
        ->where('ID_DOKTER', $id)
        ->where('ROWNUM <= 1', null, false) // Oracle-safe
        ->get()
        ->getRowArray();

    if (!$foto) {
        return $this->failNotFound('Foto dokter tidak ditemukan');
    }

    // Hapus file fisik
    $filePath = ROOTPATH . 'public/uploads/dokter/' . basename($foto['URL']);
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Hapus data di DB  ->where('URL', $foto['URL'])
    $model->builder()
        ->where('ID_DOKTER', $id)
        ->delete();

    return $this->respondDeleted('Foto dokter telah dihapus');
}


    // ======================
    // LIST FOTO
    // ======================
    public function index()
    {
        try {
            $db = \Config\Database::connect();

            $data = $db->table('DOKTER_FOTO DF')
                ->select('
                    DF.ID_DOKTER,
                    DF.URL,
                    D.NAMA_DOKTER
                ')
                ->join('DOKTER D', 'D.ID_DOKTER = DF.ID_DOKTER', 'left')
                ->orderBy('D.NAMA_DOKTER', 'ASC')
                ->get()
                ->getResultArray();

            if (empty($data)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => false,
                    'message' => 'Data tidak ditemukan'
                ]);
            }

            return $this->response->setStatusCode(200)->setJSON([
                'status'  => true,
                'message' => 'Data ditemukan',
                'data'    => $data
            ]);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Gagal mengambil data',
                'error'   => $e->getMessage()
            ]);
        }
    }

    // ======================
    // LIST DOKTER
    // ======================
    public function listDokter()
    {
    // Buat koneksi ke model DokterFotoModel (bisa juga bikin model khusus Dokter)
    $db = \Config\Database::connect($this->request->dbGroup ?? 'oracle');

    $query = $db->query("SELECT ID_DOKTER, NAMA_DOKTER FROM DOKTER WHERE KODEDOKTER IS NOT NULL ORDER BY NAMA_DOKTER");
    $result = $query->getResultArray();

    return $this->respond($result);
     }

}
