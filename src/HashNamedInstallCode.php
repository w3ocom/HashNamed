<?php
namespace w3ocom\HashNamed;

class HashNamedInstallCode extends HashNamedCore
{
    /**
     * Install code with specified type to HashNamed-local-dir
     * 
     * @param string $code
     * @param string $type
     * @param bool $save_hashnamed
     * @return array
     * @throws \Exception
     */
    public static function installHashNamedCode(string $code, string $type = 'php-function', bool $save_hashnamed = true): array {
        // check local-cache-dir
        if (empty(self::getLocalRepo())) {
            throw new \Exception("HashNamed LOCAL-cache-dir MUST specified before");
        }
        
        $h_arr = self::prepareCode($code, $type);
        if (!$h_arr) {
            throw new \Exception("This code is not valid for this type: $type");
        }
        
        $hash40hex = substr($h_arr['hash'], 0, 40);

        $hashnamed_name = self::$type_prefix[$type] . $hash40hex;

        // what name can be used to call this object
        $call_name = $save_hashnamed ? $hashnamed_name : $h_arr['name'];

        // add namespace if need
        $namespace = $h_arr['namespace'] ?? '';
        $call_name = $namespace . '\\' . $call_name;
        
        // Start creating a file path to save the code.
        // 1) Make is path of sub-folder
        $local_file = self::getLocalRepo() . substr($hash40hex, 0, 2) . DIRECTORY_SEPARATOR;
        
        // 2) Create this sub-folder if not exist
        if (empty(realpath($local_file)) && !mkdir($local_file)) {
            throw new \Exception("Can't create subdir in local-cache: $local_file");
        }
        
        // 3) finish creating the local-file name
        $local_file .= $hash40hex;
        
        // prepare data_for_write
        $data_for_write = substr($code, $h_arr['_h'][0]);
        if ($save_hashnamed) {
            $h_arr['renamed'] = $hashnamed_name;
            $data_for_write = str_replace($h_arr['name'], $hashnamed_name, $data_for_write);
        }
                
        $prefix = '<'."?php\n/*\n" . HELML::ToHELML($h_arr) . "*/\n\n";

        // saving function-code to the local_file (with prefix_code in head)
        if (!file_put_contents($local_file, $prefix . $data_for_write)) {
            throw new \Exception("Can't write functoin to local-cache-file: $local_file");
        }
        
        $h_arr['call_name'] = $call_name;
        $h_arr['hashnamed_name'] = $hashnamed_name;
        $h_arr['local_file'] = $local_file;
        $h_arr['hash40hex'] = $hash40hex;
        
        return $h_arr;
    }

    public static function prepareCode(string $code, string $type): ?array {
        // switch by type
        if ($type === 'php-function') {
            // regex for function-name
            $regex = "/function[ ]+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)[\s]*[(]/";
        } elseif ($type === 'php-class') {
            // regex for class-name
            $regex = "/class[ ]+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)[\s]*/";
        } else {
            // unsupported type
            return NULL;
        }

        // Split code to header and body
        $code_body_pos = strpos($code, '{'); // this char means beginning of the body
        if (false === $code_body_pos) {
            //Not found body in the code
            return NULL;
        }
        
        // skip php-tag if presents
        $hash_begin_pos = strpos($code, '?php');
        if (false !== $hash_begin_pos) {
            $hash_begin_pos += 4;
        }
        // skip spaces from begin of code
        $hash_begin_pos += strspn($code, " \n\r", $hash_begin_pos);
        
        // skip header-comment
        if (substr($code, $hash_begin_pos, 2) === '/*') {
            if ($end_of_comment_pos = strpos($code, '*/', $hash_begin_pos + 2)) {
                $hash_begin_pos = $end_of_comment_pos + 2;
                // skip spaces after header-comment
                $hash_begin_pos += strspn($code, " \n\r", $hash_begin_pos);
            }
        }
        
        // store body_begin_pos
        $_h = [$hash_begin_pos];

        // begin make result array
        $h_arr = compact('_h', 'type');

        // cut header from code
        $code_header = substr($code, $hash_begin_pos, $code_body_pos - $hash_begin_pos);

        //detect namespace
        if (preg_match("/namespace[ ]+([a-zA-Z_\x80-\xff].*)[;]/", $code_header, $matches)) {
            $h_arr['namespace'] = trim($matches[1]);
        }
        
        if (preg_match($regex, $code_header, $matches)) {
            $h_arr['name'] = $matches[1];
        } else {
            return NULL;
        }
        
        $data_for_hash = substr($code, $hash_begin_pos);
        
        $h_arr['hash'] = hash('sha256', $data_for_hash);

        return $h_arr;
    }
    
    /**
     * Install specified function code to LOCAL-cache-dir
     * 
     * In:
     *   function_code in string
     * Out:
     *   array = Successful, parameters in keys
     * 
     * ! An exception is thrown for all errors.
     * !!! Be careful, this function does not check the validity of the function-code.
     * !!! If the function-code is not valid, you may break the execution of the program.
     * 
     * @param string $function_code
     * @param bool $save_hashnamed
     * @return string
     * @throws \Exception
     */
    public static function installFunction(string $function_code, bool $save_hashnamed = true): array {

        // install HashNamed code with type php-function
        $h_arr = self::installHashNamedCode($function_code, 'php-function', $save_hashnamed);

        // loading function from saved-file
        $local_file = $h_arr['local_file']; 
        require_once $local_file;

        self::$loaded_hashnamed_arr[$h_arr['hash40hex']] = $h_arr;
        
        // checking for a successful function definition
        $call_name = $h_arr['call_name'];
        if (!function_exists($call_name)) {
            throw new \Exception("Function $call_name was not installed, but local file was created: $local_file");
        }
        
        return $h_arr;
    }

    /**
     * Install specified class code to LOCAL-cache-dir
     * 
     * In:
     *   class_code in string
     * Out:
     *   array = Successful, parameters in keys
     * 
     * ! An exception is thrown for all errors.
     * !!! Be careful, this function does not check the validity of the class-code.
     * !!! If the class-code is not valid, you may break the execution of the program.
     * 
     * @param string $class_code
     * @param bool $save_hashnamed
     * @return string
     * @throws \Exception
     */
    public static function installClass(string $class_code, bool $save_hashnamed = true): array {

        // install HashNamed code with type php-function
        $h_arr = self::installHashNamedCode($class_code, 'php-class', $save_hashnamed);

        // loading class code from saved-file
        $local_file = $h_arr['local_file']; 
        require_once $local_file;

        self::$loaded_hashnamed_arr[$h_arr['hash40hex']] = $h_arr;
        
        $call_name = $h_arr['call_name'];
        // checking for a successful class definition
        if (!class_exists($call_name)) {
            throw new \Exception("Class $call_name was not installed, but local file was created: $local_file");
        }
        
        return $h_arr;
    }
}
