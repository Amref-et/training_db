<?php

namespace App\Support;

use InvalidArgumentException;

class QrCodeSvg
{
    private const VERSION = 10;
    private const SIZE = 57;
    private const DATA_CODEWORDS = 274;
    private const EC_CODEWORDS_PER_BLOCK = 18;
    private const BLOCK_DATA_LENGTHS = [68, 68, 69, 69];
    private const ALIGNMENT_POSITIONS = [6, 28, 50];
    private const MASK = 0;

    /** @var array<int, array<int, bool>> */
    private array $modules = [];

    /** @var array<int, array<int, bool>> */
    private array $functionModules = [];

    public static function svg(string $text, int $scale = 4, int $border = 4): string
    {
        $qr = new self($text);

        return $qr->toSvg($scale, $border);
    }

    private function __construct(private readonly string $text)
    {
        $byteLength = strlen($this->text);

        if ($byteLength > 271) {
            throw new InvalidArgumentException('QR payload is too long.');
        }

        for ($y = 0; $y < self::SIZE; $y++) {
            $this->modules[$y] = array_fill(0, self::SIZE, false);
            $this->functionModules[$y] = array_fill(0, self::SIZE, false);
        }

        $this->drawFunctionPatterns();
        $this->drawCodewords($this->addEccAndInterleave($this->makeDataCodewords()));
    }

    private function toSvg(int $scale, int $border): string
    {
        $dimension = (self::SIZE + ($border * 2)) * $scale;
        $paths = [];

        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if ($this->modules[$y][$x]) {
                    $paths[] = 'M'.(($x + $border) * $scale).' '.(($y + $border) * $scale).'h'.$scale.'v'.$scale.'h-'.$scale.'z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$dimension.' '.$dimension.'" width="'.$dimension.'" height="'.$dimension.'" role="img" aria-label="QR code"><rect width="100%" height="100%" fill="#fff"/><path fill="#111827" d="'.implode('', $paths).'"/></svg>';
    }

    private function drawFunctionPatterns(): void
    {
        $this->drawFinderPattern(0, 0);
        $this->drawFinderPattern(self::SIZE - 7, 0);
        $this->drawFinderPattern(0, self::SIZE - 7);

        for ($i = 8; $i < self::SIZE - 8; $i++) {
            $this->setFunctionModule($i, 6, $i % 2 === 0);
            $this->setFunctionModule(6, $i, $i % 2 === 0);
        }

        foreach (self::ALIGNMENT_POSITIONS as $x) {
            foreach (self::ALIGNMENT_POSITIONS as $y) {
                $nearTop = $y === 6;
                $nearLeft = $x === 6;
                $nearRight = $x === self::ALIGNMENT_POSITIONS[array_key_last(self::ALIGNMENT_POSITIONS)];
                $nearBottom = $y === self::ALIGNMENT_POSITIONS[array_key_last(self::ALIGNMENT_POSITIONS)];

                if (($nearTop && $nearLeft) || ($nearTop && $nearRight) || ($nearBottom && $nearLeft)) {
                    continue;
                }

                $this->drawAlignmentPattern($x, $y);
            }
        }

        $this->setFunctionModule(8, self::SIZE - 8, true);
        $this->drawFormatBits();
        $this->drawVersionBits();
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;

                if ($xx < 0 || $xx >= self::SIZE || $yy < 0 || $yy >= self::SIZE) {
                    continue;
                }

                $dark = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                    && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));

                $this->setFunctionModule($xx, $yy, $dark);
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function drawFormatBits(): void
    {
        $bits = $this->formatBits();

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, (($bits >> $i) & 1) !== 0);
        }

        $this->setFunctionModule(8, 7, (($bits >> 6) & 1) !== 0);
        $this->setFunctionModule(8, 8, (($bits >> 7) & 1) !== 0);
        $this->setFunctionModule(7, 8, (($bits >> 8) & 1) !== 0);

        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule(8, self::SIZE - 1 - $i, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(self::SIZE - 15 + $i, 8, (($bits >> $i) & 1) !== 0);
        }
    }

    private function drawVersionBits(): void
    {
        $bits = self::VERSION << 12;

        for ($i = 0; $i < 12; $i++) {
            $bits = (($bits << 1) ^ (($bits >> 11) * 0x1F25)) & 0x1FFFFF;
        }

        $bits = (self::VERSION << 12) | $bits;

        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) !== 0;
            $a = self::SIZE - 11 + ($i % 3);
            $b = intdiv($i, 3);

            $this->setFunctionModule($a, $b, $dark);
            $this->setFunctionModule($b, $a, $dark);
        }
    }

    private function formatBits(): int
    {
        $data = (1 << 3) | self::MASK; // Error correction level L.
        $bits = $data << 10;

        for ($i = 0; $i < 10; $i++) {
            $bits = (($bits << 1) ^ (($bits >> 9) * 0x537)) & 0x7FFF;
        }

        return (($data << 10) | $bits) ^ 0x5412;
    }

    /**
     * @return array<int, int>
     */
    private function makeDataCodewords(): array
    {
        $bits = [];
        $this->appendBits($bits, 0b0100, 4);
        $this->appendBits($bits, strlen($this->text), 16);

        foreach (unpack('C*', $this->text) ?: [] as $byte) {
            $this->appendBits($bits, $byte, 8);
        }

        $capacity = self::DATA_CODEWORDS * 8;
        $this->appendBits($bits, 0, min(4, $capacity - count($bits)));

        while (count($bits) % 8 !== 0) {
            $bits[] = false;
        }

        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;
            foreach ($chunk as $bit) {
                $value = ($value << 1) | ($bit ? 1 : 0);
            }
            $codewords[] = $value;
        }

        for ($pad = 0xEC; count($codewords) < self::DATA_CODEWORDS; $pad ^= 0xEC ^ 0x11) {
            $codewords[] = $pad;
        }

        return $codewords;
    }

    /**
     * @param array<int, bool> $bits
     */
    private function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = (($value >> $i) & 1) !== 0;
        }
    }

    /**
     * @param array<int, int> $data
     * @return array<int, int>
     */
    private function addEccAndInterleave(array $data): array
    {
        $blocks = [];
        $offset = 0;

        foreach (self::BLOCK_DATA_LENGTHS as $length) {
            $block = array_slice($data, $offset, $length);
            $blocks[] = [
                'data' => $block,
                'ecc' => $this->reedSolomonRemainder($block, self::EC_CODEWORDS_PER_BLOCK),
            ];
            $offset += $length;
        }

        $result = [];
        $maxDataLength = max(self::BLOCK_DATA_LENGTHS);

        for ($i = 0; $i < $maxDataLength; $i++) {
            foreach ($blocks as $block) {
                if (isset($block['data'][$i])) {
                    $result[] = $block['data'][$i];
                }
            }
        }

        for ($i = 0; $i < self::EC_CODEWORDS_PER_BLOCK; $i++) {
            foreach ($blocks as $block) {
                $result[] = $block['ecc'][$i];
            }
        }

        return $result;
    }

    /**
     * @param array<int, int> $data
     * @return array<int, int>
     */
    private function reedSolomonRemainder(array $data, int $degree): array
    {
        $generator = $this->reedSolomonGenerator($degree);
        $message = array_merge($data, array_fill(0, $degree, 0));

        for ($i = 0, $dataLength = count($data); $i < $dataLength; $i++) {
            $coefficient = $message[$i];

            if ($coefficient === 0) {
                continue;
            }

            foreach ($generator as $j => $factor) {
                $message[$i + $j] ^= $this->gfMultiply($factor, $coefficient);
            }
        }

        return array_slice($message, -$degree);
    }

    /**
     * @return array<int, int>
     */
    private function reedSolomonGenerator(int $degree): array
    {
        $result = [1];

        for ($i = 0; $i < $degree; $i++) {
            $result = $this->polyMultiply($result, [1, $this->gfPow($i)]);
        }

        return $result;
    }

    /**
     * @param array<int, int> $left
     * @param array<int, int> $right
     * @return array<int, int>
     */
    private function polyMultiply(array $left, array $right): array
    {
        $result = array_fill(0, count($left) + count($right) - 1, 0);

        foreach ($left as $i => $leftValue) {
            foreach ($right as $j => $rightValue) {
                $result[$i + $j] ^= $this->gfMultiply($leftValue, $rightValue);
            }
        }

        return $result;
    }

    private function gfPow(int $power): int
    {
        $value = 1;

        for ($i = 0; $i < $power; $i++) {
            $value = $this->gfMultiply($value, 2);
        }

        return $value;
    }

    private function gfMultiply(int $x, int $y): int
    {
        $result = 0;

        while ($y !== 0) {
            if (($y & 1) !== 0) {
                $result ^= $x;
            }

            $x <<= 1;

            if (($x & 0x100) !== 0) {
                $x ^= 0x11D;
            }

            $y >>= 1;
        }

        return $result & 0xFF;
    }

    /**
     * @param array<int, int> $codewords
     */
    private function drawCodewords(array $codewords): void
    {
        $bits = [];

        foreach ($codewords as $codeword) {
            $this->appendBits($bits, $codeword, 8);
        }

        $bitIndex = 0;
        $upward = true;

        for ($right = self::SIZE - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }

            for ($vertical = 0; $vertical < self::SIZE; $vertical++) {
                $y = $upward ? self::SIZE - 1 - $vertical : $vertical;

                for ($x = $right; $x >= $right - 1; $x--) {
                    if ($this->functionModules[$y][$x]) {
                        continue;
                    }

                    $dark = $bits[$bitIndex] ?? false;
                    $bitIndex++;

                    if ($this->mask($x, $y)) {
                        $dark = ! $dark;
                    }

                    $this->modules[$y][$x] = $dark;
                }
            }

            $upward = ! $upward;
        }
    }

    private function mask(int $x, int $y): bool
    {
        return (($x + $y) % 2) === 0;
    }

    private function setFunctionModule(int $x, int $y, bool $dark): void
    {
        if ($x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE) {
            return;
        }

        $this->modules[$y][$x] = $dark;
        $this->functionModules[$y][$x] = true;
    }
}
