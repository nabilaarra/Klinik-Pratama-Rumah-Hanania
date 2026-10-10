<?php
session_start();

/*
|--------------------------------------------------------------------------
| KONEKSI DATABASE
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nik                = trim($_POST['nik'] ?? '');
    $nama_lengkap       = trim($_POST['nama_lengkap'] ?? '');
    $tanggal_lahir      = $_POST['tanggal_lahir'] ?? '';
    $jenis_kelamin      = $_POST['jenis_kelamin'] ?? '';
    $no_telepon         = trim($_POST['no_telepon'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $pekerjaan          = trim($_POST['pekerjaan'] ?? '');
    $status_perkawinan  = trim($_POST['status_perkawinan'] ?? '');
    $pendidikan_terakhir = trim($_POST['pendidikan_terakhir'] ?? '');
    $agama              = trim($_POST['agama'] ?? '');
    $alamat             = trim($_POST['alamat'] ?? '');
    $password           = $_POST['password'] ?? '';
    $konfirmasi         = $_POST['konfirmasi_password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

    if (
        $nik === '' ||
        $nama_lengkap === '' ||
        $tanggal_lahir === '' ||
        $jenis_kelamin === '' ||
        $no_telepon === '' ||
        $email === '' ||
        $alamat === '' ||
        $password === '' ||
        $konfirmasi === ''
    ) {
        $error = 'Data bertanda wajib (*) harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | CEK NIK SUDAH TERDAFTAR ATAU BELUM
        |--------------------------------------------------------------------------
        */

        $cekNik = $conn->prepare(
            "SELECT id_pasien FROM pasien WHERE nik = ? LIMIT 1"
        );

        $cekNik->bind_param("s", $nik);
        $cekNik->execute();
        $hasilNik = $cekNik->get_result();

        if ($hasilNik->num_rows > 0) {

            $error = 'NIK tersebut sudah terdaftar.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CEK EMAIL DI USER DAN PASIEN
            |--------------------------------------------------------------------------
            */

            $cekEmail = $conn->prepare(
                "SELECT id_user FROM users WHERE username = ? LIMIT 1"
            );

            $cekEmail->bind_param("s", $email);
            $cekEmail->execute();
            $hasilEmail = $cekEmail->get_result();

            if ($hasilEmail->num_rows > 0) {

                $error = 'Email tersebut sudah digunakan.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | PASSWORD
                |--------------------------------------------------------------------------
                */

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                |--------------------------------------------------------------------------
                | SIMPAN USER
                |
                | username = email
                | role     = pasien
                |--------------------------------------------------------------------------
                */

                $stmtUser = $conn->prepare(
                    "INSERT INTO users
                    (username, password, role)
                    VALUES (?, ?, 'pasien')"
                );

                $stmtUser->bind_param(
                    "ss",
                    $email,
                    $passwordHash
                );

                if ($stmtUser->execute()) {

                    $id_user = $conn->insert_id;

                    /*
                    |--------------------------------------------------------------------------
                    | SIMPAN DATA PASIEN
                    |--------------------------------------------------------------------------
                    */

                    $stmtPasien = $conn->prepare(
                        "INSERT INTO pasien
                        (
                            id_user,
                            nik,
                            nama_lengkap,
                            tanggal_lahir,
                            jenis_kelamin,
                            no_telepon,
                            email,
                            alamat,
                            pekerjaan,
                            status_perkawinan,
                            pendidikan_terakhir,
                            agama
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmtPasien->bind_param(
                        "isssssssssss",
                        $id_user,
                        $nik,
                        $nama_lengkap,
                        $tanggal_lahir,
                        $jenis_kelamin,
                        $no_telepon,
                        $email,
                        $alamat,
                        $pekerjaan,
                        $status_perkawinan,
                        $pendidikan_terakhir,
                        $agama
                    );

                    if ($stmtPasien->execute()) {

                        $success =
                            'Pendaftaran berhasil! Silakan login menggunakan email atau NIK dan password Anda.';

                    } else {

                        /*
                        | Jika pasien gagal disimpan,
                        | hapus user yang tadi sudah dibuat.
                        */

                        $conn->query(
                            "DELETE FROM users WHERE id_user = " . (int)$id_user
                        );

                        $error =
                            'Data gagal disimpan. Silakan coba lagi.';
                    }

                } else {

                    $error =
                        'Akun gagal dibuat. Silakan coba lagi.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Daftar Akun - Klinik Pratama Rumah Hanania</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #faf7fc;

            color: #333;
        }

        /*
        |--------------------------------------------------------------------------
        | CARD UTAMA
        |--------------------------------------------------------------------------
        */

        .register-card {

            width: 100%;
            max-width: 760px;

            margin: 0 auto;

            background: #fff;

            border-radius: 28px;

            overflow: hidden;

            box-shadow:
                0 12px 35px rgba(93, 48, 130, 0.12);
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER UNGU
        |--------------------------------------------------------------------------
        */

        .register-header {

            background:
                linear-gradient(
                    135deg,
                    #8147b8,
                    #9b63c9
                );

            color: white;

            padding: 42px 42px 38px;
        }

        .clinic-name {

            margin: 0 0 18px;

            font-size: 18px;

            font-weight: 700;

            letter-spacing: 1px;
        }

        .register-header h1 {

            margin: 0 0 16px;

            font-size: 42px;

            line-height: 1.15;

            font-weight: 700;
        }

        .register-header p {

            margin: 0;

            max-width: 650px;

            font-size: 17px;

            line-height: 1.7;

            color: rgba(255,255,255,0.95);
        }

        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .register-body {

            padding: 38px 42px 42px;
        }

        .form-title {

            margin: 0 0 8px;

            font-size: 24px;

            color: #333;
        }

        .form-subtitle {

            margin: 0 0 28px;

            color: #777;

            font-size: 14px;
        }

        .form-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 20px;
        }

        .form-group {

            display: flex;

            flex-direction: column;
        }

        .form-group.full {

            grid-column: 1 / -1;
        }

        label {

            margin-bottom: 8px;

            font-size: 14px;

            font-weight: 600;

            color: #444;
        }

        input,
        select,
        textarea {

            width: 100%;

            padding: 13px 14px;

            border: 1px solid #ddd;

            border-radius: 10px;

            font-size: 14px;

            font-family: inherit;

            outline: none;

            background: #fff;

            transition: 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {

            border-color: #8b4fbd;

            box-shadow:
                0 0 0 3px rgba(139,79,189,0.10);
        }

        textarea {

            min-height: 100px;

            resize: vertical;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            padding-right: 48px;
        }

        .toggle-password-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8c729c;
            border-radius: 8px;
            transition: color .2s, background-color .2s;
        }

        .toggle-password-btn:hover {
            color: #8147b8;
            background-color: #f3ecf9;
        }

        .toggle-password-btn svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
        }

        /*
        |--------------------------------------------------------------------------
        | ALERT
        |--------------------------------------------------------------------------
        */

        .alert {

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 22px;

            font-size: 14px;

            line-height: 1.5;
        }

        .alert-error {

            background: #fff0f0;

            color: #b42318;

            border: 1px solid #ffd1d1;
        }

        .alert-success {

            background: #edf9f0;

            color: #20743b;

            border: 1px solid #c9ebd1;
        }

        /*
        |--------------------------------------------------------------------------
        | BUTTON
        |--------------------------------------------------------------------------
        */

        .btn-register {

            width: 100%;

            margin-top: 28px;

            padding: 14px 20px;

            border: none;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #8147b8,
                    #9b63c9
                );

            color: white;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.2s;
        }

        .btn-register:hover {

            transform: translateY(-1px);

            box-shadow:
                0 7px 18px rgba(129,71,184,0.22);
        }

        /*
        |--------------------------------------------------------------------------
        | LINK LOGIN
        |--------------------------------------------------------------------------
        */

        .login-link {

            text-align: center;

            margin-top: 22px;

            font-size: 14px;

            color: #666;
        }

        .login-link a {

            color: #8147b8;

            font-weight: 700;

            text-decoration: none;
        }

        .login-link a:hover {

            text-decoration: underline;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 650px) {

            body {
                padding: 20px 12px;
            }

            .register-header {

                padding: 32px 25px;
            }

            .register-header h1 {

                font-size: 32px;
            }

            .register-header p {

                font-size: 15px;
            }

            .register-body {

                padding: 30px 25px;
            }

            .form-grid {

                grid-template-columns: 1fr;
            }

            .form-group.full {

                grid-column: auto;
            }
        }

    </style>

</head>

<body>

    <div class="register-card">

        <!-- HEADER -->

        <div class="register-header">

            <div class="clinic-name">
                KLINIK PRATAMA RUMAH HANANIA
            </div>

            <h1>
                Daftar Akun
            </h1>

        </div>


        <!-- FORM -->

        <div class="register-body">

            <?php if ($error !== ''): ?>

                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <?php 
            $curRedirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
            if ($success !== ''): 
            ?>

                <div class="alert alert-success">

                    <?= htmlspecialchars($success) ?>

                    <br><br>

                    <a href="login.php<?= $curRedirect !== '' ? '?redirect=' . urlencode($curRedirect) : '' ?>">
                        Login sekarang &rarr;
                    </a>

                </div>

            <?php endif; ?>


            <?php if ($success === ''): ?>

                <h2 class="form-title">
                    Data Diri
                </h2>

                <p class="form-subtitle">
                    Silakan isi data dengan benar.
                </p>


                <form method="POST"
                      action="daftar_akun.php">

                    <div class="form-grid">

                        <!-- NIK -->

                        <div class="form-group">

                            <label for="nik">
                                NIK
                            </label>

                            <input
                                type="text"
                                id="nik"
                                name="nik"
                                maxlength="16"
                                value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>"
                                placeholder="Masukkan NIK"
                                required
                            >

                        </div>


                        <!-- NAMA -->

                        <div class="form-group">

                            <label for="nama_lengkap">
                                Nama Lengkap
                            </label>

                            <input
                                type="text"
                                id="nama_lengkap"
                                name="nama_lengkap"
                                value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>"
                                placeholder="Nama lengkap"
                                required
                            >

                        </div>


                        <!-- TANGGAL LAHIR -->

                        <div class="form-group">

                            <label for="tanggal_lahir">
                                Tanggal Lahir
                            </label>

                            <input
                                type="date"
                                id="tanggal_lahir"
                                name="tanggal_lahir"
                                value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>"
                                required
                            >

                        </div>


                        <!-- JENIS KELAMIN -->

                        <div class="form-group">

                            <label for="jenis_kelamin">
                                Jenis Kelamin
                            </label>

                            <select
                                id="jenis_kelamin"
                                name="jenis_kelamin"
                                required
                            >

                                <option value="">
                                    Pilih jenis kelamin
                                </option>

                                <option
                                    value="L"
                                    <?= (($_POST['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>
                                >
                                    Laki-laki
                                </option>

                                <option
                                    value="P"
                                    <?= (($_POST['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>
                                >
                                    Perempuan
                                </option>

                            </select>

                        </div>


                        <!-- TELEPON -->

                        <div class="form-group">

                            <label for="no_telepon">
                                Nomor Telepon
                            </label>

                            <input
                                type="text"
                                id="no_telepon"
                                name="no_telepon"
                                value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>"
                                placeholder="08xxxxxxxxxx"
                                required
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label for="email">
                                Email *
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                placeholder="nama@email.com"
                                required
                            >

                        </div>


                        <!-- PEKERJAAN -->

                        <div class="form-group">

                            <label for="pekerjaan">
                                Pekerjaan
                            </label>

                            <input
                                type="text"
                                id="pekerjaan"
                                name="pekerjaan"
                                value="<?= htmlspecialchars($_POST['pekerjaan'] ?? '') ?>"
                                placeholder="Contoh: Karyawan, Pelajar, Ibu Rumah Tangga"
                            >

                        </div>


                        <!-- STATUS PERKAWINAN -->

                        <div class="form-group">

                            <label for="status_perkawinan">
                                Status Perkawinan
                            </label>

                            <select
                                id="status_perkawinan"
                                name="status_perkawinan"
                            >
                                <option value="">-- Pilih Status --</option>
                                <?php foreach(['Belum Menikah','Menikah','Cerai Hidup','Cerai Mati'] as $sp): ?>
                                    <option value="<?= $sp ?>" <?= (($_POST['status_perkawinan'] ?? '') === $sp) ? 'selected' : '' ?>><?= $sp ?></option>
                                <?php endforeach; ?>
                            </select>

                        </div>


                        <!-- PENDIDIKAN TERAKHIR -->

                        <div class="form-group">

                            <label for="pendidikan_terakhir">
                                Pendidikan Terakhir
                            </label>

                            <select
                                id="pendidikan_terakhir"
                                name="pendidikan_terakhir"
                            >
                                <option value="">-- Pilih Pendidikan --</option>
                                <?php foreach(['Tidak Sekolah','SD','SMP','SMA/SMK','D1/D2/D3','S1','S2','S3'] as $pt): ?>
                                    <option value="<?= $pt ?>" <?= (($_POST['pendidikan_terakhir'] ?? '') === $pt) ? 'selected' : '' ?>><?= $pt ?></option>
                                <?php endforeach; ?>
                            </select>

                        </div>


                        <!-- AGAMA -->

                        <div class="form-group">

                            <label for="agama">
                                Agama
                            </label>

                            <select
                                id="agama"
                                name="agama"
                            >
                                <option value="">-- Pilih Agama --</option>
                                <?php foreach(['Islam','Kristen Protestan','Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
                                    <option value="<?= $ag ?>" <?= (($_POST['agama'] ?? '') === $ag) ? 'selected' : '' ?>><?= $ag ?></option>
                                <?php endforeach; ?>
                            </select>

                        </div>


                        <!-- ALAMAT -->

                        <div class="form-group full">

                            <label for="alamat">
                                Alamat *
                            </label>

                            <textarea
                                id="alamat"
                                name="alamat"
                                placeholder="Masukkan alamat lengkap (Jalan, RT/RW, Kelurahan, Kecamatan, Kota)"
                                required
                            ><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>

                        </div>


                        <!-- PASSWORD -->

                        <div class="form-group">

                            <label for="password">
                                Password *
                            </label>

                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Minimal 6 karakter"
                                    required
                                >
                                <button
                                    type="button"
                                    class="toggle-password-btn"
                                    onclick="togglePasswordVisibility('password', this)"
                                    aria-label="Lihat password"
                                    title="Tampilkan password"
                                >
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                        <line x1="2" x2="22" y1="2" y2="22"/>
                                    </svg>
                                </button>
                            </div>

                        </div>


                        <!-- KONFIRMASI PASSWORD -->

                        <div class="form-group">

                            <label for="konfirmasi_password">
                                Konfirmasi Password *
                            </label>

                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="konfirmasi_password"
                                    name="konfirmasi_password"
                                    placeholder="Ulangi password"
                                    required
                                >
                                <button
                                    type="button"
                                    class="toggle-password-btn"
                                    onclick="togglePasswordVisibility('konfirmasi_password', this)"
                                    aria-label="Lihat konfirmasi password"
                                    title="Tampilkan password"
                                >
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                        <line x1="2" x2="22" y1="2" y2="22"/>
                                    </svg>
                                </button>
                            </div>

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="btn-register"
                    >
                        Daftar Akun
                    </button>

                </form>


                <div class="login-link">

                    Sudah memiliki akun?

                    <a href="login.php<?= $curRedirect !== '' ? '?redirect=' . urlencode($curRedirect) : '' ?>">
                        Login di sini
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const eyeOpen = btn.querySelector('.eye-open');
    const eyeClosed = btn.querySelector('.eye-closed');

    if (input.type === 'password') {
        input.type = 'text';
        if (eyeOpen) eyeOpen.style.display = 'none';
        if (eyeClosed) eyeClosed.style.display = 'block';
        btn.setAttribute('aria-label', 'Sembunyikan password');
        btn.title = 'Sembunyikan password';
    } else {
        input.type = 'password';
        if (eyeOpen) eyeOpen.style.display = 'block';
        if (eyeClosed) eyeClosed.style.display = 'none';
        btn.setAttribute('aria-label', 'Lihat password');
        btn.title = 'Tampilkan password';
    }
}
</script>

</body>

</html>