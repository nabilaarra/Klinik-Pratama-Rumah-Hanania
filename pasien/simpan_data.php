<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
$db = (isset($conn) && $conn instanceof mysqli) ? $conn : ((isset($koneksi) && $koneksi instanceof mysqli) ? $koneksi : null);
if (!$db) { echo json_encode(['success'=>false,'message'=>'Koneksi database tidak ditemukan.']); exit; }
$db->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Metode tidak valid.']); exit; }
$idUser=(int)($_SESSION['id_user']??$_SESSION['user']['id_user']??$_SESSION['user_id']??0);
if($idUser<=0){echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu untuk menyimpan data diri.']);exit;}

$fields=[
 'nik'=>trim($_POST['nik']??''),
 'nama_lengkap'=>trim($_POST['nama_lengkap']??''),
 'tanggal_lahir'=>trim($_POST['tanggal_lahir']??''),
 'jenis_kelamin'=>trim($_POST['jenis_kelamin']??''),
 'no_telepon'=>trim($_POST['no_telepon']??''),
 'email'=>trim($_POST['email']??''),
 'alamat'=>trim($_POST['alamat']??''),
 'pekerjaan'=>trim($_POST['pekerjaan']??''),
 'status_perkawinan'=>trim($_POST['status_perkawinan']??''),
 'pendidikan_terakhir'=>trim($_POST['pendidikan_terakhir']??''),
 'agama'=>trim($_POST['agama']??''),
 'ada_rujukan'=>trim($_POST['ada_rujukan']??'Tidak'),
 'asal_rujukan'=>trim($_POST['asal_rujukan']??'')
];
if(!preg_match('/^\d{16}$/',$fields['nik'])){echo json_encode(['success'=>false,'message'=>'NIK harus terdiri dari 16 digit angka.']);exit;}
foreach(['nama_lengkap','tanggal_lahir','jenis_kelamin','no_telepon','alamat','pekerjaan','status_perkawinan','pendidikan_terakhir','agama'] as $k){if($fields[$k]===''){echo json_encode(['success'=>false,'message'=>'Lengkapi semua data diri wajib terlebih dahulu.']);exit;}}
if($fields['ada_rujukan']!=='Ya'){$fields['ada_rujukan']='Tidak';$fields['asal_rujukan']='';}

$stmt=$db->prepare('SELECT id_pasien FROM pasien WHERE id_user=? LIMIT 1');
if(!$stmt){echo json_encode(['success'=>false,'message'=>'Data pasien tidak dapat diperiksa.']);exit;}
$stmt->bind_param('i',$idUser);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$row){echo json_encode(['success'=>false,'message'=>'Data pasien belum tersedia.']);exit;}
$idPasien=(int)$row['id_pasien'];

$sets=[];$types='';$vals=[];
foreach($fields as $col=>$val){
  $chk=@$db->query("SHOW COLUMNS FROM pasien LIKE '".$db->real_escape_string($col)."'");
  if($chk&&$chk->num_rows>0){$sets[]="`$col`=?";$types.='s';$vals[]=$val;}
}
if(!$sets){echo json_encode(['success'=>false,'message'=>'Kolom data diri belum tersedia. Jalankan revisi_database.sql terlebih dahulu.']);exit;}
$types.='i';$vals[]=$idPasien;
$stmt=$db->prepare('UPDATE pasien SET '.implode(', ',$sets).' WHERE id_pasien=?');
if(!$stmt){echo json_encode(['success'=>false,'message'=>'Data diri tidak dapat disimpan.']);exit;}
$stmt->bind_param($types,...$vals);
if(!$stmt->execute()){echo json_encode(['success'=>false,'message'=>'Data diri gagal disimpan: '.$stmt->error]);$stmt->close();exit;}
$stmt->close();

foreach($fields as $k=>$v){$_SESSION[$k]=$v;}
$_SESSION['nama_lengkap']=$fields['nama_lengkap'];
$_SESSION['nik']=$fields['nik'];

echo json_encode(['success'=>true,'message'=>'Data diri berhasil disimpan.']);
