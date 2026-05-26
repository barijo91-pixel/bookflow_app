<?php
/**
 * BookSys PWA 아이콘 생성 (GD)
 * 출력: public/icons/icon-192.png, icon-512.png, icon-maskable-512.png, apple-touch-icon-180.png
 *
 * 디자인: favicon.svg 모티프 — 네이비 배경 + 흰색 책 모양 + 가운데 회색 책등
 */

function drawRoundedRect($img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
{
    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledellipse($img, $x1 + $r,     $y1 + $r,     $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r,     $y1 + $r,     $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x1 + $r,     $y2 - $r,     $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r,     $y2 - $r,     $r * 2, $r * 2, $color);
}

/** @param bool $maskable maskable=true면 safe-zone 안에 작게 그림 (배경은 가득) */
function makeIcon(int $size, string $path, bool $maskable = false): void
{
    // 4배 supersampling for AA
    $S = $size * 4;
    $img = imagecreatetruecolor($S, $S);
    imagesavealpha($img, true);
    imageantialias($img, true);

    $navy  = imagecolorallocate($img, 0x1f, 0x3a, 0x5f);
    $white = imagecolorallocate($img, 0xff, 0xff, 0xff);
    $gray  = imagecolorallocate($img, 0x9f, 0xb3, 0xc8);

    // 배경
    if ($maskable) {
        // 가득 채움 (safe zone 위해 콘텐츠는 80% 영역에 그림)
        imagefilledrectangle($img, 0, 0, $S, $S, $navy);
        $padding = (int) ($S * 0.18); // 18% safe-zone
    } else {
        // 둥근 사각형
        $r = (int) ($S * 0.22);
        drawRoundedRect($img, 0, 0, $S - 1, $S - 1, $r, $navy);
        $padding = (int) ($S * 0.22);
    }

    // 책 모양 (열린 책)
    $bookW = $S - 2 * $padding;
    $bookH = (int) ($bookW * 0.78);
    $bookX = $padding;
    $bookY = (int) (($S - $bookH) / 2);

    // 좌우 페이지 (흰색)
    $spineW = max(2, (int) ($S * 0.012));
    $halfW = (int) (($bookW - $spineW) / 2);
    imagefilledrectangle($img, $bookX,                          $bookY, $bookX + $halfW,             $bookY + $bookH, $white);
    imagefilledrectangle($img, $bookX + $halfW + $spineW,       $bookY, $bookX + $bookW,             $bookY + $bookH, $white);

    // 가운데 책등 (회색)
    imagefilledrectangle($img, $bookX + $halfW, $bookY - (int) ($S * 0.02),
                                 $bookX + $halfW + $spineW, $bookY + $bookH + (int) ($S * 0.02), $gray);

    // 페이지 줄 (네이비) — 3줄씩 좌우
    $lineH = max(3, (int) ($S * 0.018));
    $textPad = (int) ($S * 0.025);
    for ($i = 0; $i < 3; $i++) {
        $y = $bookY + (int) ($bookH * (0.22 + $i * 0.22));
        imagefilledrectangle($img,
            $bookX + $textPad, $y,
            $bookX + $halfW - $textPad, $y + $lineH,
            $navy);
        imagefilledrectangle($img,
            $bookX + $halfW + $spineW + $textPad, $y,
            $bookX + $bookW - $textPad, $y + $lineH,
            $navy);
    }

    // 다운스케일 (AA)
    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    $trans = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $trans);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $S, $S);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    imagepng($out, $path);
    imagedestroy($img);
    imagedestroy($out);
    echo "  ✓ $path (".filesize($path)." bytes)\n";
}

$root = __DIR__ . '/../public/icons';
echo "Generating BookSys PWA icons → $root\n";

makeIcon(192, "$root/icon-192.png");
makeIcon(512, "$root/icon-512.png");
makeIcon(512, "$root/icon-maskable-512.png", maskable: true);
makeIcon(180, "$root/apple-touch-icon-180.png");

// favicon용도 추가
makeIcon(96,  "$root/icon-96.png");
makeIcon(48,  "$root/icon-48.png");

echo "Done.\n";
