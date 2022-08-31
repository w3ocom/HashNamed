<?php
namespace w3ocom\HashNamed;

class HashNamed {
    
    /**
     * left part of URL for downloading hashnamed-objects
     * @var array<string>
     */
    protected static array $repositories_arr = [];
    
    /**
     * Store h_arr for hashnamed-obj when local-file is included
     * @var array<array>
     */
    protected static array $loaded_hashnamed_arr = [];
    
    /**
     * Store call_name for each loaded object
     * @var array<string>
     */
    protected static array $names_to_loaded_map = [];
    
    public static bool $accept_remote_renamed = true;
    
    protected static array $type_prefix = [
        'php-class' => 'C_',
        'php-function' => 'fn_',
    ];
    
    /**
     * Key to specify LOCAL-cache-dir in self::$repositories_arr
     */
    public const LOCAL_REPO_KEY = 'LOCAL';

    /**
     * Init HashNamed is easy way to set local_cache_dir
     * 
     *  Alternatively, you may not call this constructor, but instead call this static function:
     *   HashNamed::addRepositories([self::LOCAL_REPO_KEY => 'YOUR_LOCAL_CACHE_DIR'])
     * 
     *  Additionally, in the constructor, you can specify an array of remote repositories
     *  or, repositories can be added later by calling this function:
     *   HashNamed::addRepositories(['repository-url1', 'repository-url2', ...])
     * 
     * @param string $local_cache_dir
     * @param null|array<string> $repositories_arr
     * @throws \Exception
     */
    public function __construct(string $local_cache_dir, ?array $repositories_arr = NULL) {

        $local_cache_real_path = realpath($local_cache_dir . DIRECTORY_SEPARATOR);
        if (empty($local_cache_real_path)) {
            throw new \Exception("Local cache-dir not exist, but required: $local_cache_dir");
        }
        self::addRepositories([self::LOCAL_REPO_KEY => $local_cache_real_path . DIRECTORY_SEPARATOR]);
        
        if ($repositories_arr) {
            self::addRepositories($repositories_arr);
        }
    }
    
    /**
     * Add repositories URL-s array to HashNamed-class static array
     * 
     * @param array<string> $repositories_arr
     * @return int
     * @throws \InvalidArgumentException
     */
    public static function addRepositories(array $repositories_arr): int {
        $add_cnt = 0;
        foreach($repositories_arr as $repo_key => $repo_URL_left) {
            if (!is_string($repo_URL_left) || empty($repo_URL_left)) {
                throw new \InvalidArgumentException("Only string URL is accepted");
            }
            if (is_numeric($repo_key)) {
                $old_index = array_search($repo_URL_left, self::$repositories_arr);
                if (false === $old_index) {
                    self::$repositories_arr[] = $repo_URL_left;
                    $add_cnt++;
                }
            } else {
                self::$repositories_arr[$repo_key] = $repo_URL_left;
                $add_cnt++;
            }
        }
        return $add_cnt;
    }

    /**
     * Try load HashNamed-code from LOCAL-cache-dir,
     *   if not found in LOCAL, tries to download from all known remote repositories
     *   in case of successful download code saves to LOCAL-cache-dir
     * 
     * In:
     *   $hash40hex = 40-chars of hex-encoded left 20-bytes from sha256(code)
     *   $save_hashnamed = true for save code with hashnamed-names, false for save code with source names
     *   $expected_type = if null any types are accepted, otherwise only specified type will accepted
     * Out:
     *   NULL = code NOT found locally or remotely
     *   array = success, have keys:
     *      [call_name] = final name to call this object
     *      [hashnamed_name] = the name of this object, constructed from its body-hash
     *      [local_file] = string, full-path of the local file where code is saved
     *      [h_arr] = array of header-values
     *       ... and other keys...
     * 
     * @param string $hash40hex
     * @param string $prefix_code
     * @return array|null
     * @throws \Exception
     */
    public static function loadHashNamedCode(string $hash40hex, bool $save_hashnamed = true, ?string $expected_type = null): ?array {

        // arguments verification
        if (strlen($hash40hex) !== 40) {
            throw new \Exception("Invalid hash40hex length");
        }
        
        if (empty(self::$repositories_arr[self::LOCAL_REPO_KEY])) {
            throw new \Exception("HashNamed LOCAL-cache-dir MUST specified before");
        }

        $repo_subdir = substr($hash40hex, 0, 2) . '/';
        
        foreach(self::$repositories_arr as $repo_key => $repo_URL_left) {
            $full_URL = $repo_URL_left . $repo_subdir . $hash40hex;
            $data_src = @file_get_contents($full_URL);
            if (empty($data_src)) continue; // No data - skip repo
            
            $h_arr = HELML::getHeader($data_src, [
                'hash' => 1,
                'name' => 1,
                'type' => 1,
                'renamed' => 0,
                'namespace' => 0,
            ]);
            
            if (!$h_arr) continue; // Header not detected, invalid format
            
            // compare hash from header
            if (substr($h_arr['hash'], 0, 40) !== $hash40hex) continue;
            
            if ($expected_type && ($expected_type !== $h_arr['type'])) {
                throw new \Exception("Hashnamed object $hash40hex was found, but it has an unexpected type:" . $h_arr['type']);
            }
            
            // check 'renamed'
            if ($need_rename_back = !empty($h_arr['renamed'])) {
                // if code contain hashnamed names, we need to rename back for verification
                if (($repo_key !== self::LOCAL_REPO_KEY) && !self::$accept_remote_renamed) {
                    // skip because we won't accept renamend code from remote repository
                    continue;
                }
            }
            
            // check 'type'
            if (empty(self::$type_prefix[$h_arr['type']])) continue; // undefined type

            // create hashnamed-name by type from header
            $hashnamed_name = self::$type_prefix[$h_arr['type']] . $hash40hex;

            // cut body-data from source
            $data_for_hash = \substr($data_src, $h_arr['_h'][0]);
            
            if ($need_rename_back) {
                // rename back: replace all hashnamed-names to real-name
                $data_for_hash = str_replace($hashnamed_name, $h_arr['name'], $data_for_hash);
            }
            
            // calculate hash from body
            $hash_of_body = hash('sha256', $data_for_hash);

            // hash verification
            if (substr($hash_of_body, 0, 40) === $hash40hex) {
                // GOOD! Hash is equal
                
                // get namespace
                $namespace = $h_arr['namespace'] ?? '';

                // what name can be used to call this object
                $call_name = $save_hashnamed ? $hashnamed_name : $h_arr['name'];
                $call_name = $namespace . '\\' . $call_name;

                self::$names_to_loaded_map[$hashnamed_name] = $call_name;
                self::$names_to_loaded_map[$h_arr['name']] = $call_name;

                
                if ($repo_key === self::LOCAL_REPO_KEY) {
                    // data received from local-cache
                    if ($need_rename_back === $save_hashnamed) {
                        // if already named as need
                        $h_arr['call_name'] = $call_name;
                        $h_arr['hashnamed_name'] = $hashnamed_name;
                        $h_arr['local_file'] = $full_URL;

                        return $h_arr;
                    }
                }

                // file to save in local-cache-dir
                $local_file = self::$repositories_arr[self::LOCAL_REPO_KEY] . $repo_subdir;
                if (empty(realpath($local_file)) && !mkdir($local_file)) {
                    throw new \Exception("Can't create sub-dir for storage data: $local_file");
                }
                $local_file .= $hash40hex;
                
                if ($save_hashnamed) {
                    $h_arr['renamed'] = 1;
                    $data_for_write = str_replace($h_arr['name'], $hashnamed_name, $data_for_hash);
                } else {
                    $data_for_write = $data_for_hash;
                    $h_arr['renamed'] = null;
                }
                $prefix = '<'."?php\n/*\n" . HELML::ToHELML($h_arr) . "*/\n\n";
                
                if (!file_put_contents($local_file, $prefix . $data_for_write)) {
                    throw new \Exception("Can't save received data to local-cache-dir");
                }
                
                $h_arr['call_name'] = $call_name;
                $h_arr['hashnamed_name'] = $hashnamed_name;
                $h_arr['local_file'] = $local_file;

                // Success
                return $h_arr;
            }
        }
        // NOT FOUND
        return NULL;
    }
    
    public static function installHashNamedCode(string $code, string $type = 'php-function', bool $save_hashnamed = true): array {
        // check local-cache-dir
        if (empty(self::$repositories_arr[self::LOCAL_REPO_KEY])) {
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
        $local_file = self::$repositories_arr[self::LOCAL_REPO_KEY]  . substr($hash40hex, 0, 2) . DIRECTORY_SEPARATOR;
        
        // 2) Create this sub-folder if not exist
        if (empty(realpath($local_file)) && !mkdir($local_file)) {
            throw new \Exception("Can't create subdir in local-cache: $local_file");
        }
        
        // 3) finish creating the local-file name
        $local_file .= $hash40hex;
        
        // prepare data_for_write
        $data_for_write = substr($code, $h_arr['_h'][0]);
        if ($save_hashnamed) {
            $h_arr['renamed'] = 1;
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
        // skip spaces
        $hash_begin_pos += strspn($code, " \n\r", $hash_begin_pos);

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
     * @return string|null
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
