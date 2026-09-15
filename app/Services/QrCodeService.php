<?php
/**
 * Gerador local de QR Code em SVG para 2FA/TOTP.
 * Não usa API externa e não envia o segredo para terceiros.
 * Suporta modo byte, correção L e versões 1 a 10, suficiente para URIs otpauth do HUB.
 */
class QrCodeService {
  private const TOTAL_CODEWORDS = [1=>26,2=>44,3=>70,4=>100,5=>134,6=>172,7=>196,8=>242,9=>292,10=>346];
  private const ECC_CODEWORDS_PER_BLOCK_L = [1=>7,2=>10,3=>15,4=>20,5=>26,6=>18,7=>20,8=>24,9=>30,10=>18];
  private const NUM_ECC_BLOCKS_L = [1=>1,2=>1,3=>1,4=>1,5=>1,6=>2,7=>2,8=>2,9=>2,10=>4];
  private const ALIGNMENT_POSITIONS = [
    1=>[], 2=>[6,18], 3=>[6,22], 4=>[6,26], 5=>[6,30], 6=>[6,34],
    7=>[6,22,38], 8=>[6,24,42], 9=>[6,26,46], 10=>[6,28,50],
  ];

  private int $version;
  private int $size;
  private array $modules = [];
  private array $functionModules = [];

  public static function svg(string $text, int $scale = 5, int $border = 4): string {
    $qr = new self();
    $qr->encode($text);
    return $qr->toSvg($scale, $border);
  }

  public static function dataUri(string $text, int $scale = 5, int $border = 4): string {
    return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $scale, $border));
  }

  private function encode(string $text): void {
    $data = array_values(unpack('C*', $text));
    $this->version = $this->chooseVersion(count($data));
    $this->size = $this->version * 4 + 17;
    $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
    $this->functionModules = array_fill(0, $this->size, array_fill(0, $this->size, false));

    $dataCodewords = $this->createDataCodewords($data, $this->version);
    $allCodewords = $this->addErrorCorrectionAndInterleave($dataCodewords, $this->version);

    $this->drawFunctionPatterns();
    $this->drawCodewords($allCodewords);

    $bestMask = 0;
    $bestPenalty = PHP_INT_MAX;
    $baseModules = $this->modules;
    for ($mask = 0; $mask < 8; $mask++) {
      $this->modules = $baseModules;
      $this->applyMask($mask);
      $this->drawFormatBits($mask);
      $penalty = $this->penaltyScore();
      if ($penalty < $bestPenalty) {
        $bestPenalty = $penalty;
        $bestMask = $mask;
      }
    }
    $this->modules = $baseModules;
    $this->applyMask($bestMask);
    $this->drawFormatBits($bestMask);
  }

  private function chooseVersion(int $byteLength): int {
    for ($version = 1; $version <= 10; $version++) {
      $countBits = $version < 10 ? 8 : 16;
      if ($version < 10 && $byteLength > 255) continue;
      $neededBits = 4 + $countBits + ($byteLength * 8);
      $capacityBits = $this->numDataCodewords($version) * 8;
      if ($neededBits <= $capacityBits) return $version;
    }
    throw new RuntimeException('Texto grande demais para o gerador QR interno do HUB.');
  }

  private function createDataCodewords(array $bytes, int $version): array {
    $bits = [];
    $this->appendBits($bits, 0b0100, 4); // modo byte
    $this->appendBits($bits, count($bytes), $version < 10 ? 8 : 16);
    foreach ($bytes as $b) $this->appendBits($bits, $b, 8);

    $capacityBits = $this->numDataCodewords($version) * 8;
    $this->appendBits($bits, 0, min(4, $capacityBits - count($bits)));
    while ((count($bits) % 8) !== 0) $bits[] = 0;

    $codewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
      $v = 0;
      for ($j = 0; $j < 8; $j++) $v = ($v << 1) | $bits[$i + $j];
      $codewords[] = $v;
    }
    for ($pad = 0xEC; count($codewords) < $this->numDataCodewords($version); $pad ^= 0xEC ^ 0x11) {
      $codewords[] = $pad;
    }
    return $codewords;
  }

  private function appendBits(array &$bits, int $value, int $length): void {
    for ($i = $length - 1; $i >= 0; $i--) $bits[] = ($value >> $i) & 1;
  }

  private function numDataCodewords(int $version): int {
    return self::TOTAL_CODEWORDS[$version] - self::ECC_CODEWORDS_PER_BLOCK_L[$version] * self::NUM_ECC_BLOCKS_L[$version];
  }

  private function addErrorCorrectionAndInterleave(array $data, int $version): array {
    $numBlocks = self::NUM_ECC_BLOCKS_L[$version];
    $ecLen = self::ECC_CODEWORDS_PER_BLOCK_L[$version];
    $dataLen = count($data);
    $numShortBlocks = $numBlocks - ($dataLen % $numBlocks);
    $shortBlockLen = intdiv($dataLen, $numBlocks);

    $blocks = [];
    $offset = 0;
    for ($i = 0; $i < $numBlocks; $i++) {
      $blockDataLen = $shortBlockLen + ($i >= $numShortBlocks ? 1 : 0);
      $blockData = array_slice($data, $offset, $blockDataLen);
      $offset += $blockDataLen;
      $blocks[] = [
        'data' => $blockData,
        'ecc'  => $this->reedSolomonRemainder($blockData, $ecLen),
      ];
    }

    $result = [];
    $maxDataLen = $shortBlockLen + 1;
    for ($i = 0; $i < $maxDataLen; $i++) {
      foreach ($blocks as $block) {
        if ($i < count($block['data'])) $result[] = $block['data'][$i];
      }
    }
    for ($i = 0; $i < $ecLen; $i++) {
      foreach ($blocks as $block) $result[] = $block['ecc'][$i];
    }
    return $result;
  }

  private function reedSolomonRemainder(array $data, int $degree): array {
    $divisor = $this->reedSolomonDivisor($degree);
    $result = array_fill(0, $degree, 0);
    foreach ($data as $b) {
      $factor = $b ^ $result[0];
      array_shift($result);
      $result[] = 0;
      for ($i = 0; $i < $degree; $i++) {
        $result[$i] ^= $this->gfMultiply($divisor[$i], $factor);
      }
    }
    return $result;
  }

  private function reedSolomonDivisor(int $degree): array {
    $result = array_fill(0, $degree, 0);
    $result[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
      for ($j = 0; $j < $degree; $j++) {
        $result[$j] = $this->gfMultiply($result[$j], $root);
        if ($j + 1 < $degree) $result[$j] ^= $result[$j + 1];
      }
      $root = $this->gfMultiply($root, 0x02);
    }
    return $result;
  }

  private function gfMultiply(int $x, int $y): int {
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
      $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
      if ((($y >> $i) & 1) !== 0) $z ^= $x;
    }
    return $z;
  }

  private function drawFunctionPatterns(): void {
    $this->drawFinderPattern(3, 3);
    $this->drawFinderPattern($this->size - 4, 3);
    $this->drawFinderPattern(3, $this->size - 4);

    foreach (self::ALIGNMENT_POSITIONS[$this->version] as $x) {
      foreach (self::ALIGNMENT_POSITIONS[$this->version] as $y) {
        $nearTop = $y <= 8;
        $nearLeft = $x <= 8;
        $nearRight = $x >= $this->size - 9;
        if (($nearTop && $nearLeft) || ($nearTop && $nearRight) || (!$nearTop && $nearLeft && $y >= $this->size - 9)) continue;
        $this->drawAlignmentPattern($x, $y);
      }
    }

    for ($i = 0; $i < $this->size; $i++) {
      if (!$this->functionModules[6][$i]) $this->setFunctionModule($i, 6, $i % 2 === 0);
      if (!$this->functionModules[$i][6]) $this->setFunctionModule(6, $i, $i % 2 === 0);
    }

    $this->drawFormatBits(0);
    $this->setFunctionModule(8, $this->size - 8, true);
    if ($this->version >= 7) $this->drawVersionBits();
  }

  private function drawFinderPattern(int $cx, int $cy): void {
    for ($dy = -4; $dy <= 4; $dy++) {
      for ($dx = -4; $dx <= 4; $dx++) {
        $x = $cx + $dx;
        $y = $cy + $dy;
        if ($x < 0 || $x >= $this->size || $y < 0 || $y >= $this->size) continue;
        $dist = max(abs($dx), abs($dy));
        $this->setFunctionModule($x, $y, $dist === 3 || $dist <= 1);
      }
    }
  }

  private function drawAlignmentPattern(int $cx, int $cy): void {
    for ($dy = -2; $dy <= 2; $dy++) {
      for ($dx = -2; $dx <= 2; $dx++) {
        $dist = max(abs($dx), abs($dy));
        $this->setFunctionModule($cx + $dx, $cy + $dy, $dist !== 1);
      }
    }
  }

  private function setFunctionModule(int $x, int $y, bool $black): void {
    if ($x < 0 || $x >= $this->size || $y < 0 || $y >= $this->size) return;
    $this->modules[$y][$x] = $black;
    $this->functionModules[$y][$x] = true;
  }

  private function drawFormatBits(int $mask): void {
    $data = (1 << 3) | $mask; // ECC L = 01
    $rem = $data;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    $bits = (($data << 10) | $rem) ^ 0x5412;

    for ($i = 0; $i <= 5; $i++) $this->setFunctionModule(8, $i, (($bits >> $i) & 1) !== 0);
    $this->setFunctionModule(8, 7, (($bits >> 6) & 1) !== 0);
    $this->setFunctionModule(8, 8, (($bits >> 7) & 1) !== 0);
    $this->setFunctionModule(7, 8, (($bits >> 8) & 1) !== 0);
    for ($i = 9; $i < 15; $i++) $this->setFunctionModule(14 - $i, 8, (($bits >> $i) & 1) !== 0);

    for ($i = 0; $i < 8; $i++) $this->setFunctionModule($this->size - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
    for ($i = 8; $i < 15; $i++) $this->setFunctionModule(8, $this->size - 15 + $i, (($bits >> $i) & 1) !== 0);
    $this->setFunctionModule(8, $this->size - 8, true);
  }

  private function drawVersionBits(): void {
    $rem = $this->version;
    for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
    $bits = ($this->version << 12) | $rem;
    for ($i = 0; $i < 18; $i++) {
      $black = (($bits >> $i) & 1) !== 0;
      $a = $this->size - 11 + ($i % 3);
      $b = intdiv($i, 3);
      $this->setFunctionModule($a, $b, $black);
      $this->setFunctionModule($b, $a, $black);
    }
  }

  private function drawCodewords(array $codewords): void {
    $bits = [];
    foreach ($codewords as $cw) {
      for ($i = 7; $i >= 0; $i--) $bits[] = ($cw >> $i) & 1;
    }
    $bitIndex = 0;
    $upward = true;
    for ($right = $this->size - 1; $right >= 1; $right -= 2) {
      if ($right === 6) $right = 5;
      for ($vert = 0; $vert < $this->size; $vert++) {
        $y = $upward ? $this->size - 1 - $vert : $vert;
        for ($j = 0; $j < 2; $j++) {
          $x = $right - $j;
          if (!$this->functionModules[$y][$x]) {
            $this->modules[$y][$x] = ($bitIndex < count($bits)) && $bits[$bitIndex] === 1;
            $bitIndex++;
          }
        }
      }
      $upward = !$upward;
    }
  }

  private function applyMask(int $mask): void {
    for ($y = 0; $y < $this->size; $y++) {
      for ($x = 0; $x < $this->size; $x++) {
        if ($this->functionModules[$y][$x]) continue;
        if ($this->maskBit($mask, $x, $y)) $this->modules[$y][$x] = !$this->modules[$y][$x];
      }
    }
  }

  private function maskBit(int $mask, int $x, int $y): bool {
    return match ($mask) {
      0 => (($x + $y) % 2) === 0,
      1 => ($y % 2) === 0,
      2 => ($x % 3) === 0,
      3 => (($x + $y) % 3) === 0,
      4 => ((intdiv($x, 3) + intdiv($y, 2)) % 2) === 0,
      5 => (($x * $y) % 2 + ($x * $y) % 3) === 0,
      6 => ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0,
      7 => ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0,
      default => false,
    };
  }

  private function penaltyScore(): int {
    $penalty = 0;
    for ($y = 0; $y < $this->size; $y++) {
      $runColor = false; $runLen = 0;
      for ($x = 0; $x < $this->size; $x++) {
        if ($x === 0 || $this->modules[$y][$x] !== $runColor) {
          if ($runLen >= 5) $penalty += 3 + ($runLen - 5);
          $runColor = $this->modules[$y][$x]; $runLen = 1;
        } else $runLen++;
      }
      if ($runLen >= 5) $penalty += 3 + ($runLen - 5);
    }
    for ($x = 0; $x < $this->size; $x++) {
      $runColor = false; $runLen = 0;
      for ($y = 0; $y < $this->size; $y++) {
        if ($y === 0 || $this->modules[$y][$x] !== $runColor) {
          if ($runLen >= 5) $penalty += 3 + ($runLen - 5);
          $runColor = $this->modules[$y][$x]; $runLen = 1;
        } else $runLen++;
      }
      if ($runLen >= 5) $penalty += 3 + ($runLen - 5);
    }
    for ($y = 0; $y < $this->size - 1; $y++) {
      for ($x = 0; $x < $this->size - 1; $x++) {
        $c = $this->modules[$y][$x];
        if ($c === $this->modules[$y][$x+1] && $c === $this->modules[$y+1][$x] && $c === $this->modules[$y+1][$x+1]) $penalty += 3;
      }
    }
    $dark = 0;
    for ($y = 0; $y < $this->size; $y++) for ($x = 0; $x < $this->size; $x++) if ($this->modules[$y][$x]) $dark++;
    $total = $this->size * $this->size;
    $k = (int)(abs($dark * 20 - $total * 10) / $total);
    return $penalty + $k * 10;
  }

  private function toSvg(int $scale, int $border): string {
    $scale = max(2, min(12, $scale));
    $border = max(2, min(8, $border));
    $dimension = ($this->size + $border * 2) * $scale;
    $parts = [];
    for ($y = 0; $y < $this->size; $y++) {
      for ($x = 0; $x < $this->size; $x++) {
        if ($this->modules[$y][$x]) {
          $parts[] = '<rect x="'.(($x + $border) * $scale).'" y="'.(($y + $border) * $scale).'" width="'.$scale.'" height="'.$scale.'"/>';
        }
      }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="QR Code 2FA" viewBox="0 0 '.$dimension.' '.$dimension.'" width="'.$dimension.'" height="'.$dimension.'"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">'.implode('', $parts).'</g></svg>';
  }
}
