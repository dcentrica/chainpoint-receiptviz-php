<?php

namespace Dcentrica\Viz;

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package dcentrica-chainpoint-tools
 * @license BSD-3
 *
 * Rather simple conversion and transformation routines for chainpoint hashes,
 * very loosely based on NodeJS' Buffers.
 */
class HashUtils
{
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
     * Take an array of bytes, and produce a raw binary representation of it.
     *
     * @param  array  $bytes
     * @return string $chars A raw binary string
     */
    public static function buffer_to_bin(array $bytes) : string
    {
        $chars = '';

        foreach ($bytes as $byte) {
            $chars .= chr($byte);
        }

        return $chars;
    }

    /**
     * Convert an array of bytes derived from a hash, back into that original
     * hash (aka a digest).
     *
     * @param  array $dec An array of decimals
     * @return string
     */
    public static function buffer_digest_from(array $bytes) : string
    {
        $hex = '';

        foreach($bytes as $int) {
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
     * Concatenate two "buffers".
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
     * Reverses the endianness of a given string to allow for interopability
     * between the Bitcoin blockchain and other systems, namely Tierion's
     * Chainpoint network.
     *
     * @param  string $string
     * @return string
     */
    public static function switch_endian(string $string) : string
    {
        $type = self::is_hex($string) ? 'hex' : 'utf8';
        $buffer = self::buffer_from($string, $type);

        return self::buffer_digest_from(array_reverse($buffer));
    }

    /**
     * Determine if the passed $string in hex format.
     *
     * @param  string $string
     * @return bool
     */
    public static function is_hex(string $string) : bool
    {
        return ctype_xdigit($string);
    }

}
