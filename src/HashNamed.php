<?php
namespace w3ocom\HashNamed;

class HashNamed extends HashNamedCore
{

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
     * @return string
     * @throws \Exception
     */
    public static function installFunction(string $function_code): array {

        // install HashNamed code with type php-function
        $h_arr = self::installHashNamedCode($function_code, 'php-function');

        $local_file = $h_arr['local_file']; 
        // loading function from saved-file
        require_once $local_file;
        self::$loaded_hashnamed_arr[$h_arr['hash40hex']] = $h_arr;
        
        $call_name = $h_arr['call_name'];
        // checking for a successful function definition
        if (!function_exists($call_name)) {
            throw new \Exception("Function $call_name was not installed, but local file was created: $local_file");
        }
        
        // return function name only
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
     * @return string
     * @throws \Exception
     */
    public static function installClass(string $class_code): array {

        // install HashNamed code with type php-function
        $h_arr = self::installHashNamedCode($class_code, 'php-class');

        $local_file = $h_arr['local_file']; 
        // loading function from saved-file
        require_once $local_file;
        self::$loaded_hashnamed_arr[$h_arr['hash40hex']] = $h_arr;
        
        $call_name = $h_arr['call_name'];
        // checking for a successful function definition
        if (!class_exists($call_name)) {
            throw new \Exception("Class $call_name was not installed, but local file was created: $local_file");
        }
        
        // return function name only
        return $h_arr;
    }
    
    /**
     * Tries to load function
     *   if not found in LOCAL, tries to download from all known remote repositories
     * 
     * In:
     *   string function name (for example: "fn_a446bf034aa85ff330a58bb97250bc1072269bfe")
     * Out:
     *   array = Successful. Function found, loaded and installed. See array keys.
     *   null = Not found (hashnamed object not found)
     *
     * @param string $name
     * @return array|null
     * @throws \Exception (from loadHashNamedCode)
     */
    public static function loadFunction(string $name, bool $save_hashnamed = true): ?array {
        // fn_{40 chars}, total expected 43 chars
        if(strlen($name) !== 43) {
            throw new \Exception("Bad HashNamed-function name. Expected: fn_hash40 (total 43 chars exactly)");
        }
        
        $hash40hex = substr($name, 3);
     
        $h_arr = self::loadHashNamedCode($hash40hex, $save_hashnamed, 'php-function');
        if (!$h_arr) {
            //hashnamed-object not found
            return NULL;
        }
        
        require_once $h_arr['local_file'];

        if (!function_exists($h_arr['call_name'])) {
            throw new \Exception("Function not defined, but code was loaded");
        }
        
        return $h_arr;
    }

    public static function loadByName($name): ?array {
        // auto-detect calling method by name
        $l = strlen($name);
        if (43 === $l) {
            // fn_... = calling function
            $expected_type = 'php-function';
            $hash40hex = substr($name, 3);
        } elseif(42 === $l) {
            // C_... = create class instance
            $expected_type = 'php-class';
            $hash40hex = substr($name, 2);
        } elseif (44 === $l) {
            // obj_... Hash40hex = load hashnamed object and return h_arr
            $expected_type = null; // any type
            $hash40hex = substr($name, 4);
        } else {
            //throw new \Exception("Bad length of called name");
            return NULL;
        }
        
        if (isset(self::$loaded_hashnamed_arr[$hash40hex])) {
            $h_arr = self::$loaded_hashnamed_arr[$hash40hex];
        } else {
            // object not found, try to load
            $h_arr = self::loadHashNamedCode($hash40hex, true, $expected_type);
            if (!$h_arr) {
                return NULL;
            }
            if (44 !== $l) {
                self::$loaded_hashnamed_arr[$hash40hex] = $h_arr;
                require_once $h_arr['local_file'];
            }
        }

        return $h_arr;
    }
   
    public static function __callStatic(string $name, array $arguments) {
        $h_arr = self::loadByName($name);

        if (!$h_arr) {
            throw new \Exception("Hashnamed object $name not found");
        }

        $call_name = $h_arr['call_name'];

        $l = strlen($name);
        if ($l === 43) {
            // call function and return result
            return $call_name(...$arguments);
        } elseif ($l === 42) {
            // create and return instance of class
            return new $call_name(...$arguments);
        }

        return $h_arr;
    }
    
    
    public function __call(string $name, array $arguments) {
        return self::__callStatic($name, $arguments);
    }
}
