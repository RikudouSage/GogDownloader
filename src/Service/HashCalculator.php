<?php

namespace App\Service;

use RuntimeException;

final class HashCalculator
{
    public function getHash(string $filePath): string
    {
        $fp = fopen($filePath, 'r');
        if ($fp === false) {
            throw new RuntimeException("Could not open file '{$filePath}' for reading");
        }

        try {
            $hash = hash_init('md5');
            while (!feof($fp)) {
                $chunk = fread($fp, 100 * 2 ** 20); // read 100 MB
                hash_update($hash, $chunk);
            }

            return hash_final($hash);
        } finally {
            fclose($fp);
        }
    }
}
