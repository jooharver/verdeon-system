<?php

namespace App\Helpers;

use kornrunner\Keccak;
use Elliptic\EC;

class EthereumSigner
{
    /**
     * Konversi super aman dari Float/String ke format Wei (18 desimal)
     */
    public static function toWei($amount)
    {
        $amount = str_replace(',', '.', (string)$amount);
        if (stripos($amount, 'E') !== false) {
            $amount = sprintf('%.18F', (float)$amount);
        }

        $parts = explode('.', $amount);
        $whole = ltrim($parts[0], '0') ?: '0';
        $fraction = isset($parts[1]) ? $parts[1] : '';
        
        $fraction = str_pad($fraction, 18, '0', STR_PAD_RIGHT);
        $fraction = substr($fraction, 0, 18);
        
        $wei = ltrim($whole . $fraction, '0');
        return $wei === '' ? '0' : $wei;
    }

    /**
     * KONVERTER MANUAL BCMath ke Hexadesimal
     */
    private static function bc2hex($number)
    {
        $hex = '';
        $number = preg_replace('/[^0-9]/', '', (string)$number);
        if ($number === '' || $number === '0') return '0';

        while (bccomp($number, '0', 0) > 0) {
            $rem = bcmod($number, '16');
            $hex = dechex((int)$rem) . $hex;
            $number = bcdiv($number, '16', 0);
        }
        return $hex;
    }

    public static function signMintData($issuerWallet, $projectId, $amountInWei)
    {
        $privateKey = env('SERVER_PRIVATE_KEY');
        if (!$privateKey) {
            throw new \Exception("Server Private Key tidak ditemukan di .env!");
        }

        // Pastikan Private Key benar-benar bersih dari 0x, spasi, dan kutip
        $privateKey = trim($privateKey);
        $privateKey = str_replace(['0x', '"', "'"], '', $privateKey);

        $issuerHex = str_replace('0x', '', strtolower($issuerWallet));
        $projectIdHex = str_pad(dechex($projectId), 64, '0', STR_PAD_LEFT);
        
        $amountHex = str_pad(self::bc2hex($amountInWei), 64, '0', STR_PAD_LEFT);

        // Bungkus data
        $packed = hex2bin($issuerHex . $projectIdHex . $amountHex);
        $messageHash = Keccak::hash($packed, 256);

        // Tambahkan Prefix standar Ethereum
        $prefix = "\x19Ethereum Signed Message:\n32";
        $ethSignedMessageHash = Keccak::hash($prefix . hex2bin($messageHash), 256);

        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey);
        
        // 👉 FIX TERBESAR: Hapus hex2bin di sini! Library ini minta String Hex, bukan Biner.
        $signature = $keyPair->sign($ethSignedMessageHash, ['canonical' => true]);

        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = dechex($signature->recoveryParam + 27);

        return '0x' . $r . $s . $v;
    }
}