<?php
class Secrets {
  public static function mask(?string $value): string {
    $value = (string)$value;
    if ($value === '') return '';
    $len = strlen($value);
    if ($len <= 8) return str_repeat('•', $len);
    return substr($value,0,4).str_repeat('•', max(6,$len-8)).substr($value,-4);
  }
  public static function keepIfMasked(string $posted, ?string $current): string {
    if ($posted === '' || str_contains($posted, '•')) return (string)$current;
    return $posted;
  }
}
