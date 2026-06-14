<?php

// ==============================================================================
// 1. KONFIGURASI AWAL
// ==============================================================================

// PASTE HASIL SCAN QR JAJANAN RATU KE DALAM VARIABEL INI:
// (Contoh string di bawah hanya ilustrasi, ganti dengan string asli Anda)
$qrisStatis = "00020101021126650013ID.CO.BCA.WWW011893600014000281005202150008850028100520303UMI51440014ID.CO.QRIS.WWW0215ID10253738323320303UMI5204581453033605802ID5912JAJANAN RATU6006MALANG61056513762070703A0163043788"; 

// Tentukan nominal dinamis yang ingin ditagihkan
// Cek parameter nominal dari GET request (jika ada)
$nominal = isset($_GET['nominal']) ? (int)$_GET['nominal'] : 20000; 
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// ==============================================================================
// 2. PROSES UBAH KE DINAMIS
// ==============================================================================

// A. Buang 4 digit CRC lama di paling belakang string
$qrisTanpaCrc = substr($qrisStatis, 0, -4);

// B. Ubah tipe dari Statis menjadi Dinamis (010211 -> 010212)
$qrisDinamis = str_replace("010211", "010212", $qrisTanpaCrc);

// C. Bentuk Tag 54 (Transaction Amount)
$nominalStr = (string)$nominal;
$panjangNominalStr = str_pad(strlen($nominalStr), 2, '0', STR_PAD_LEFT);
$tag54 = "54" . $panjangNominalStr . $nominalStr; // Hasil: 540515000

// D. Sisipkan Tag 54 tepat sebelum Tag 58 (Country Code: 5802ID)
$posisi58 = strpos($qrisDinamis, "5802ID");
if ($posisi58 !== false) {
    $qrisDinamis = substr_replace($qrisDinamis, $tag54, $posisi58, 0);
}

// E. Hitung CRC16-CCITT baru untuk string yang sudah disisipkan nominal
$crc = 0xFFFF;
for ($c = 0; $c < strlen($qrisDinamis); $c++) {
    $crc ^= ord($qrisDinamis[$c]) << 8;
    for ($i = 0; $i < 8; $i++) {
        if ($crc & 0x8000) {
            $crc = ($crc << 1) ^ 0x1021;
        } else {
            $crc = $crc << 1;
        }
    }
}
// Pastikan hasil CRC selalu 4 digit Hexadecimal
$crcHex = str_pad(strtoupper(dechex($crc & 0xFFFF)), 4, '0', STR_PAD_LEFT);

// F. Gabungkan string dinamis dengan CRC yang baru
$qrisFinal = $qrisDinamis . $crcHex;

// G. Jika dipanggil via AJAX, kembalikan JSON dan hentikan script
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'qris' => $qrisFinal,
        'url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrisFinal)
    ]);
    exit;
}

// ==============================================================================
// 3. TAMPILKAN HASIL KE BROWSER
// ==============================================================================
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Generator QRIS Dinamis</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: auto; }
        textarea { width: 100%; padding: 10px; font-family: monospace; }
        .qr-container { text-align: center; margin-top: 20px; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
    </style>
</head>
<body>

    <h2>QRIS Dinamis Jajanan Ratu</h2>
    <p>Nominal Tagihan: <strong>Rp <?php echo number_format($nominal, 0, ',', '.'); ?></strong></p>

    <label>String QRIS Final:</label>
    <textarea rows="6" readonly><?php echo $qrisFinal; ?></textarea>

    <div class="qr-container">
        <h3>Test Scan QR Ini:</h3>
        <p>Gunakan aplikasi m-banking atau e-wallet (Gopay, OVO, Dana) untuk mengetes apakah nominal langsung muncul.</p>
    
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($qrisFinal); ?>" alt="QRIS Dinamis">
    </div>

</body>
</html>