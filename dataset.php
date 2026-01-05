<?php
session_start();
include 'config.php';
include 'header.php';

// --- IMPORT CSV ---
if (isset($_POST['import_csv'])) {
    $filename = $_FILES['file_csv']['tmp_name'];

    if ($_FILES['file_csv']['size'] > 0) {
        $file = fopen($filename, "r");
        fgetcsv($file);

        $success_count = 0;
        while (($column = fgetcsv($file, 1000, ",")) !== FALSE) {
            $tahun = $column[0];
            $x1 = $column[1];
            $x2 = $column[2];
            $x3 = $column[3];
            $x4 = $column[4];

            $check = mysqli_query($conn, "SELECT id FROM population_data WHERE tahun = '$tahun'");
            if (mysqli_num_rows($check) == 0) {
                mysqli_query($conn, "INSERT INTO population_data (tahun, kelahiran, kematian, pindah_keluar, pindah_datang) 
                                     VALUES ('$tahun', '$x1', '$x2', '$x3', '$x4')");
                $success_count++;
            }
        }
        fclose($file);
        include 'sync_data.php';
        echo "<script>alert('$success_count Data baru berhasil diimport dan disinkronkan!'); window.location='dataset.php';</script>";
    }
}

// --- DELETE & UPDATE ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM population_data WHERE id='$id'");
    echo "<script>alert('Data berhasil dihapus!'); window.location='dataset.php';</script>";
}

if (isset($_POST['update_data'])) {
    $id_edit = $_POST['id'];
    $x1 = $_POST['x1'];
    $x2 = $_POST['x2'];
    $x3 = $_POST['x3'];
    $x4 = $_POST['x4'];
    mysqli_query($conn, "UPDATE population_data SET kelahiran='$x1', kematian='$x2', pindah_keluar='$x3', pindah_datang='$x4' WHERE id='$id_edit'");

    $set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings LIMIT 1"));
    $current_pop = $set['base_population'] ?? 0;
    $res_all = mysqli_query($conn, "SELECT * FROM population_data ORDER BY tahun ASC");

    while ($row = mysqli_fetch_assoc($res_all)) {
        $id_row = $row['id'];
        $new_y = ($row['kelahiran'] - $row['kematian']) + ($row['pindah_datang'] - $row['pindah_keluar']) + $current_pop;
        mysqli_query($conn, "UPDATE population_data SET jumlah_penduduk='$new_y' WHERE id='$id_row'");
        $current_pop = $new_y;
    }
    echo "<script>alert('Data berhasil diperbarui dan disinkronkan!'); window.location='dataset.php';</script>";
}

// --- RESET DATA ---
if (isset($_POST['reset_data'])) {
    $query_reset = mysqli_query($conn, "TRUNCATE TABLE population_data");

    if ($query_reset) {
        echo "<script>alert('Semua data berhasil dihapus!'); window.location='dataset.php';</script>";
    } else {
        echo "<script>alert('Gagal meriset data.');</script>";
    }
}

// --- DATA GRAFIK & TABEL ---
$get_setting = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$data_setting = mysqli_fetch_assoc($get_setting);
$all_data = [];
$query = mysqli_query($conn, "SELECT * FROM population_data ORDER BY tahun ASC");
while ($r = mysqli_fetch_assoc($query)) {
    $all_data[] = $r;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Data Set</h3>
    <div>
        <a href="export_sample.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download"></i> Download Format CSV
        </a>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
            <i class="bi bi-file-earmark-excel"></i> Import CSV
        </button>
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Data via CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="small text-muted">Pastikan format file Anda sesuai dengan template yang telah disediakan.</p>
                    <input type="file" name="file_csv" class="form-control" accept=".csv" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="import_csv" class="btn btn-primary w-100">Upload dan Proses</button>
                </div>
            </form>
        </div>
    </div>
</div>
<hr>

<div class="row mb-3">
    <div class="col-md-12">
        <div class="card bg-light border-0 shadow-sm">
            <div class="card-body d-flex justify-content-around align-items-center py-2">
                <div class="text-center"><span class="text-muted small">Tahun Awal </span><strong class="h5"><?= $data_setting['base_year'] ?? '-' ?></strong></div>
                <div class="vr"></div>
                <div class="text-center"><span class="text-muted small">Penduduk Existing </span><strong class="h5 text-primary"><?= number_format($data_setting['base_population'] ?? 0) ?> Jiwa</strong></div>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive card shadow-sm p-3 mb-4">
    <h5 class="card-title mb-3">Tabel Data Rinci</h5>
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark text-center">
            <tr>
                <th>No</th>
                <th>Tahun</th>
                <th>Kelahiran (X1)</th>
                <th>Kematian (X2)</th>
                <th>Pindah Keluar (X3)</th>
                <th>Pindah Datang (X4)</th>
                <th>Jumlah Penduduk (Y)</th>
                <th>Kenaikan (%)</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            // AWAL: Pembanding baris pertama adalah Penduduk Existing
            $prev_population = $data_setting['base_population'] ?? 0;

            foreach ($all_data as $row) {
                $current_population = $row['jumlah_penduduk'];
                $percentage_increase = "-";
                $text_color = "";

                if ($prev_population > 0) {
                    $increase = (($current_population - $prev_population) / $prev_population) * 100;
                    $percentage_increase = number_format($increase, 2) . "%";
                    $text_color = ($increase >= 0) ? "text-success" : "text-danger";
                }
            ?>
                <tr>
                    <td class="text-center"><?= $no ?></td>
                    <td class="text-center"><?= $row['tahun'] ?></td>
                    <td class="text-center text-success"><?= $row['kelahiran'] ?></td>
                    <td class="text-center text-danger"><?= $row['kematian'] ?></td>
                    <td class="text-center text-warning"><?= $row['pindah_keluar'] ?></td>
                    <td class="text-center text-info"><?= $row['pindah_datang'] ?></td>
                    <td class="text-center fw-bold bg-light"><?= number_format($current_population) ?></td>
                    <td class="text-center fw-bold <?= $text_color ?>"><?= $percentage_increase ?></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id'] ?>">Edit</button>
                        <a href="dataset.php?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus data ini?')">Hapus</a>
                    </td>
                </tr>

                <div class="modal fade" id="modalEdit<?= $row['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title">Edit Data Thn <?= $row['tahun'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <div class="mb-2"><label>Tahun</label><input type="number" name="tahun_view" class="form-control" value="<?= $row['tahun'] ?>" readonly disabled></div>
                                    <div class="mb-2"><label>Kelahiran (X1)</label><input type="number" name="x1" class="form-control" value="<?= $row['kelahiran'] ?>" required></div>
                                    <div class="mb-2"><label>Kematian (X2)</label><input type="number" name="x2" class="form-control" value="<?= $row['kematian'] ?>" required></div>
                                    <div class="mb-2"><label>Pindah Keluar (X3)</label><input type="number" name="x3" class="form-control" value="<?= $row['pindah_keluar'] ?>" required></div>
                                    <div class="mb-2"><label>Pindah Datang (X4)</label><input type="number" name="x4" class="form-control" value="<?= $row['pindah_datang'] ?>" required></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" name="update_data" class="btn btn-primary">Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php
                $no++;
                // Update pembanding ke jumlah penduduk tahun ini untuk iterasi berikutnya
                $prev_population = $current_population;
            } ?>
        </tbody>
    </table>
    <div class="d-flex justify-content-end mt-3">
        <form method="POST" onsubmit="return confirm('PERINGATAN! Seluruh data akan terhapus. Tindakan ini tidak dapat dibatalkan.')">
            <button type="submit" name="reset_data" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash3"></i> Reset Data
            </button>
            <a href="regresi.php" class="btn btn-primary btn-sm">
                Lanjut ke Analisis <i class="bi bi-arrow-right-circle"></i>
            </a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>