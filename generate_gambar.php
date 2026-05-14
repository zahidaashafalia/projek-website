<?php
// File: generate_gambar.php
$folder = __DIR__ . '/assets/images/';
if (!file_exists($folder)) {
    mkdir($folder, 0777, true);
}

// Daftar Gambar yang akan dibuat
$images = [
    'mie1.jpg' => ['color' => [255, 100, 100], 'text' => 'MIE YAMIN'],
    'mie2.jpg' => ['color' => [255, 200, 100], 'text' => 'MIE AYAM'],
    'mercon1.jpg' => ['color' => [255, 50, 50], 'text' => 'MERCON'],
    'mercon2.jpg' => ['color' => [200, 0, 0], 'text' => 'MERCON LVL2'],
    'dimsum1.jpg' => ['color' => [255, 255, 200], 'text' => 'HAKAU'],
    'dimsum2.jpg' => ['color' => [255, 255, 150], 'text' => 'SIOMAY'],
    'dimsum3.jpg' => ['color' => [255, 220, 100], 'text' => 'DIMSUM'],
    'drink1.jpg' => ['color' => [200, 150, 100], 'text' => 'ES TEH'],
    'drink2.jpg' => ['color' => [255, 165, 0], 'text' => 'ES JERUK'],
    'drink3.jpg' => ['color' => [150, 100, 50], 'text' => 'KOPI SUSU'],
    'drink4.jpg' => ['color' => [150, 255, 100], 'text' => 'JUS ALPUKAT'],
    'drink5.jpg' => ['color' => [200, 230, 255], 'text' => 'AIR MINERAL'],
];

echo "<h3>Membuat Gambar Placeholder...</h3>";

foreach ($images as $filename => $data) {
    $img = imagecreatetruecolor(400, 300);
    $bg = imagecolorallocate($img, $data['color'][0], $data['color'][1], $data['color'][2]);
    imagefill($img, 0, 0, $bg);
    
    $textColor = imagecolorallocate($img, 0, 0, 0);
    $fontFile = 5; 
    $text = $data['text'];
    $x = (400 / 2) - ((strlen($text) * imagefontwidth($fontFile)) / 2);
    $y = (300 / 2) - (imagefontheight($fontFile) / 2);
    
    imagestring($img, $fontFile, $x, $y, $text, $textColor);
    imagejpeg($img, $folder . $filename);
    imagedestroy($img);
    
    echo "✅ $filename created.<br>";
}

echo "<br><h3>Selesai! Gambar tersimpan di folder assets/images.</h3>";
echo "<a href='index.php' style='font-size:20px; color:blue;'>Buka Aplikasi</a>";
?>