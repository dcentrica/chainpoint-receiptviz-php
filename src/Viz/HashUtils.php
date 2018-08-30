<?php

namespace Dcentrica\Viz;

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package dcentrica-chainpoint-tools
 * @license BSD-3
 *
 * Rather simple conversion and transformation routines for chainpoint hashes.
 */
class HashUtils
{
    /**
     * Simple hashing in a chainpoint receipt context. Deals with e.g. 'sha-256'
     * and 'sha-256-x2'.
     *
     * @param  string $func The hash function to use as defined in a chainpoint
     *                      receipt.
     * @param  string $subj The string to hash.
     * @return string       The hashed string.
     */
    public static function hash(string $func, string $subj): string
    {
        $parts = explode('-', $func);
        $func = "{$parts[0]}{$parts[1]}";

        if (sizeof($parts) === 3) {
            return hash($func, hash($func, $subj));
        }

        return hash($func, $subj);
    }

    /**
     * Take any string and convert it to hex.
     *
     * @param  string $string
     * @return string
     */
    public static function str_to_hex(string $string) : string
    {
        return implode('', unpack('H*', $string));
    }

    /**
     * Take an array of bytes, and produces a stringy representation of it.
     *
     * @param  array  $bytes
     * @return string
     */
    public static function buffer_to_char(array $bytes) : string
    {
        $chars = '';

        foreach ($bytes as $byte) {
            $chars .= chr($byte);
        }

        return $chars;
    }

    /**
     * Convert an array of decimals derived from a hash, back into that original
     * hash - its "digest".
     *
     * @param  array $dec An array of decimals
     * @return string
     */
    public static function buffer_digest_from(array $dec) : string
    {
        $hex = '';

        foreach($dec as $int) {
            // Left pad single hex values with zeroes, to match chainpoint hashes
            $hex .= str_pad(dechex($int), 2, '0', STR_PAD_LEFT);
        }

        return $hex;
    }

    /**
     * Build an array of decimals from a hex-string (e.g. a sha256 hash) input.
     *
     * @param  string $input
     * @param  string $type
     * @return array
     */
    public static function buffer_from(string $input, $type = 'utf8') : array
    {
        $output = [];

        if ($type === 'hex') {
            foreach(str_split($input, 2) as $char) {
                $output[] = hexdec($char);
            }
        } else if ($type === 'utf8') {
            $output = self::buffer_from(self::str_to_hex($input), 'hex');
        }

        return $output;
    }

    /**
     * Concatenate two buffers.
     *
     * @param  array $lhs
     * @param  array $rhs
     * @return array
     */
    public static function buffer_concat(array $lhs, array $rhs) : array
    {
        return array_merge($lhs, $rhs);
    }

    /**
     * Reverses the endianness of a given hash to allow for interopability
     * between the Bitcoin blockchain and other systems, namely the Chainpoint
     * network.
     *
     * @param  string $hash
     * @return string
     */
    public static function switch_endian(string $hash) : string
    {
        $buffer = self::buffer_from($hash);

        return self::buffer_digest_from(array_reverse($buffer));
    }

}
