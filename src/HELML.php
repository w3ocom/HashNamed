<?php

namespace w3ocom\HashNamed;

class HELML {
    public static function getHeader(string $data_src, array $fields_arr = [], bool $get_all_fields = true): ?array {

        // 1) cut header to $h_data
        $helml_end_pos = 0;
        foreach(["\n", "\r", "\n\r"] as $eol) {
            if ($helml_end_pos = strpos($data_src, $eol.$eol)) break;
        }
        if (!$helml_end_pos) {
            // helml header not found, invalid code
            return NULL;
        }
        $h_data = substr($data_src, 0, $helml_end_pos);

        // 2) get required fields from header
        $h_arr = ['_h' => [$helml_end_pos + strlen($eol) * 2, 'eol' => $eol]];
        foreach($fields_arr as $field_name => $is_required) {
            $i = strpos($h_data, $field_name . ':');
            if (false === $i) {
                // field not found
                if ($is_required) {
                    return NULL;
                }
                $h_arr[$field_name] = null;
                continue;
            }
            $data_pos = $i + strlen($field_name) + 1;
            while (' ' === $h_data[$data_pos]) $data_pos++;
            $j = strpos($h_data, $eol, $data_pos);
            $h_arr[$field_name] = substr($h_data, $data_pos, ($j ? ($j - $data_pos) : null));
        }
        
        // 3) if get_all_fields ON
        if ($get_all_fields) {
            $prev_name = 0;
            $all_rows_arr = explode($eol, $h_data);
            foreach($all_rows_arr as $row) {
                $i = strpos($row, ':');
                if (false === $i) continue;
                $name = substr($row, 0, $i);
                if ($i) $prev_name = $name; else $name = $prev_name;
                $data = trim(substr($row, $i + 1));
                if (key_exists($name, $h_arr)) {
                    // skip fields, grabbed by fields_arr rules
                    if (key_exists($name, $fields_arr)) continue;
                    if (!is_array($h_arr[$name])) {
                        $h_arr[$name] = [$h_arr[$name]];
                    }
                    $h_arr[$name][] = $data;
                } else {
                    $h_arr[$name] = $data;
                }
            }
        }
        
        return $h_arr;
    }
    
    public static function toHELML(array $arr, ?string $eol = null): string {

        // calculate EOL
        if (isset($arr['_h']['eol'])) $eol = $arr['_h']['eol'];
        $eol = $eol ?? "\n";

        // walt $arr and push result string to $out_arr
        $out_arr = [];
        foreach($arr as $name => $data) {
            if (is_array($data)) {
                if ($name === '_h') continue; // skip header-prefix
                $st = reset($data);
                do {
                    $out_arr[] = $name . ': ' . (string)$st;
                } while (false !== ($st = next($data)));
            } elseif (!is_null($data)) {
                $out_arr[] = $name . ': ' . (string)$data;
            }
        }
        return implode($eol, $out_arr) . $eol;
    }
}
