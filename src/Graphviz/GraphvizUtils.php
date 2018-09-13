<?php

namespace Dcentrica\Graphviz;

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package chainpoint-receiptviz-php
 * @license BSD-3
 *
 * Rather simple conversion and transformation routines for chainpoint hashes,
 * very loosely based on NodeJS' Buffers.
 */
class GraphvizUtils
{
    /**
     * Generates dotfile syntax to render an HTML table. Takes a 2-dimensional
     * array as a parameter where each value is an array of data for each <td>.
     *
     * @param  array $meta
     * @return string
     */
    public static function table(array $meta) : string
    {
        $table = 'arset [label=<';
        $table .= '<table border="0">';

        foreach ($meta as $heading => $data) {
            $table .= '<tr>';
            $table .= '<td>' . $heading . '</td>';
            $table .= '<td>' . $data . '</td>';
            $table .= '</tr>';
        }

        $table .= '</table>';
        $table .= '>, ];';

        return $table;
    }
}
