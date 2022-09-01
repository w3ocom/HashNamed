<?php
namespace w3ocom\HashNamed;
/**
 * HELML = HEader-Like Markup Language
 *  Example:
hash: 21479c639387cf0bea8a7fb3ef69106c0bb6def19d3ca6b1e90221765f3385c7
name: HashNamedTestClass
type: php-class
renamed: C_21479c639387cf0bea8a7fb3ef69106c0bb6def1
 * 
 */

class HELML {

    /**
     * Detect HELML-header in data_src and parse if found
     * 
     * @param string $data_src
     * @param array $fields_arr
     * @param bool $get_all_fields
     * @return array|null
     */
    
    public static function getHeader(string $data_src, array $fields_arr = [], bool $get_all_fields = true): ?array {

        // 1) find header from begin to the position of double empty lines
        $helml_end_pos = 0;
        foreach(["\n", "\r", "\n\r"] as $eol) {
            if ($helml_end_pos = strpos($data_src, $eol.$eol)) break;
        }
        if (!$helml_end_pos) {
            // helml header not found, invalid code
            return NULL;
        }
        // 2) cut header to $h_data (all data before double-empty-lines)
        $h_data = substr($data_src, 0, $helml_end_pos);

        // 3) get requested fields from header
        $h_arr = ['_h' => [$helml_end_pos + strlen($eol) * 2, 'eol' => $eol]];
        foreach($fields_arr as $field_name => $is_required) {
            $i = strpos($h_data, $field_name . ':');
            if (false === $i) {
                // field not found
                if ($is_required) {
                    return NULL;
                }
                // if requested field not found, but not required, set it as null
                $h_arr[$field_name] = null;
                continue;
            }
            
            // set data-pos to point after name:
            $data_pos = $i + strlen($field_name) + 1;

            // skipping optional spaces
            while (' ' === $h_data[$data_pos]) $data_pos++;
            
            // find end of line
            $j = strpos($h_data, $eol, $data_pos);
            // cut value from data_pos to end of line or end of data
            $h_arr[$field_name] = substr($h_data, $data_pos, ($j ? ($j - $data_pos) : null));
        }
        
        // 4) (optional) when get_all_fields is true
        if ($get_all_fields) {
            $prev_name = 0;
            // covert HELML-header to array of strings
            $all_rows_arr = explode($eol, $h_data);
            // walking all strings
            foreach($all_rows_arr as $row) {
                // find : divider
                $i = strpos($row, ':');
                // skip if divider not found
                if (false === $i) continue;
                // get name
                $name = substr($row, 0, $i);
                // if name is empty, use prev_name as name
                // otherwise set prev_name as name
                if ($i) $prev_name = $name; else $name = $prev_name;
                // cut value data and remove spaces from begin and end
                $data = trim(substr($row, $i + 1));
                
                // is this name already exist in h_arr ?
                if (key_exists($name, $h_arr)) {
                    // name already exist
                    // skip fields, grabbed by fields_arr rules
                    if (key_exists($name, $fields_arr)) continue;

                    // if name not specified in fields_arr
                    if (!is_array($h_arr[$name])) {
                        // convert value to array
                        $h_arr[$name] = [$h_arr[$name]];
                    }
                    // add element to name-array
                    $h_arr[$name][] = $data;
                } else {
                    // save data in key [name]
                    $h_arr[$name] = $data;
                }
            }
        }
        
        return $h_arr;
    }
    
    public static function toHELML(array $arr, ?string $eol = null): string
    {
        // calculate EOL
        if (isset($arr['_h']['eol'])) $eol = $arr['_h']['eol'];
        $eol = $eol ?? "\n";

        $out_arr = [];
        // walk $arr and stor result string to $out_arr
        foreach($arr as $name => $data) {
            if (is_array($data)) {
                // array-elements supported
                if ($name === '_h') continue; // skip header-prefix

                // store array data as multiple elements with the same key
                $st = reset($data);
                do {
                    $out_arr[] = $name . ': ' . (string)$st;
                } while (false !== ($st = next($data)));

            } elseif (!is_null($data)) { // null-elements skipping

                // element must be string or stringable
                $out_arr[] = $name . ': ' . (string)$data;
            }
        }

        // convert out_arr to string and return
        return implode($eol, $out_arr) . $eol;
    }
}
