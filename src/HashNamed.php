<?php
namespace w3ocom\HashNamed;

class HashNamed {
    
    /**
     * left part of URL for downloading hashnamed-objects
     * @var array<string>
     */
    protected static array $repositories_arr = [];
    
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
     * Tries to load and install function from LOCAL-cache-dir, then,
     *   if not found in LOCAL, tries to download from all known remote repositories
     * 
     * In:
     *   string function name (for example: "fn_a446bf034aa85ff330a58bb97250bc1072269bfe")
     * Out:
     *   null = Successful. Function found, loaded and installed.
     *   string = error describe
     *
     * @param string $name
     * @return string|null
     * @throws \Exception (from loadHashNamedCode)
     */
    public static function loadFunction($name): ?string {
        // fn_{40 chars}, total expected 43 chars
        if(strlen($name) !== 43) {
            return "Bad HashNamed-function name. Expected: fn_hash40 (total 43 chars exactly)";
        }
        
        $prefix_code = '<' . '?php function ' . $name;
        
        $hash40hex = substr($name, 3); //fn_hash40
     
        $local_file = self::loadHashNamedCode($hash40hex, $prefix_code);
        
        if (!$local_file) {
            //function not found
            return "HashNamed-Function not found locally and remotely: $name";
        }
        
        require_once $local_file;

        return function_exists($name) ? NULL : "Function not defined, but code loaded";
    }

    /**
     * Try load HashNamed-code from LOCAL-cache-dir,
     *   if not found in LOCAL, tries to download from all known remote repositories
     *   in case of successful download code saves to LOCAL-cache-dir
     * 
     * In:
     *   $hash40hex = 40-chars of hex-encoded left 20-bytes from sha256(code)
     *   $prefix_code = the left part of the code, which will be removed if it is present
     * Out:
     *   NULL = code NOT found locally or remotely
     *   string = full-path of the local file where code is saved
     * 
     * @param string $hash40hex
     * @param string $prefix_code
     * @return string|null
     * @throws \Exception
     */
    public static function loadHashNamedCode(string $hash40hex, string $prefix_code, string $left_cut = '({'): ?string {

        // arguments verification
        if (strlen($hash40hex) !== 40) {
            throw new \Exception("Invalid hash40hex length");
        }
        
        if (empty(self::$repositories_arr[self::LOCAL_REPO_KEY])) {
            throw new \Exception("HashNamed LOCAL-cache-dir MUST specified before");
        }

        $prefix_len = strlen($prefix_code);
        
        $repo_subdir = substr($hash40hex, 0, 2) . '/';
        
        foreach(self::$repositories_arr as $repo_key => $repo_URL_left) {
            $full_URL = $repo_URL_left . $repo_subdir . $hash40hex;
            $data = @file_get_contents($full_URL);
            if (empty($data)) continue; // No data - skip repo
                
            // try to remove prefix_code (if present)
            $prefix_equal = $prefix_len && (substr($data, 0, $prefix_len) === $prefix_code);
            if ($prefix_equal) {
                $data = substr($data, $prefix_len);
            } else {
                // if prefix_code is different, use left_cut chars
                $i = strcspn($data, $left_cut);
                if ($i) {
                    $data = substr($data, $i);
                }
            }

            // hash verification
            if (substr(hash('sha256', $data), 0, 40) === $hash40hex) {
                // GOOD! Hash is equal
                if ($prefix_equal && ($repo_key === self::LOCAL_REPO_KEY)) {
                    // data received from local-cache, return file-name
                    return $full_URL;
                }
                // If data received from remote-repo, save it to local-cache-dir
                $local_file = self::$repositories_arr[self::LOCAL_REPO_KEY] . $repo_subdir;
                if (empty(realpath($local_file)) && !mkdir($local_file)) {
                    throw new \Exception("Can't create sub-dir for storage data: $local_file");
                }
                $local_file .= $hash40hex;
                $data = $prefix_code . $data;
                if (!file_put_contents($local_file, $data)) {
                    throw new \Exception("Can't save received data to local-cache-dir");
                }
                // Success
                return $local_file;
            }
        }
        // NOT FOUND
        return NULL;
    }
    
    /**
     * Install specified function code to LOCAL-cache-dir
     * 
     * In:
     *   function_code in string
     * Out:
     *   string = function name 
     *   (it is HashNamed-function name, like "fn_a446bf034aa85ff330a58bb97250bc1072269bfe")
     * 
     * ! An exception is thrown for all errors.
     * !!! Be careful, this function does not check the validity of the function-code.
     * !!! If the function-code is not valid, you may break the execution of the program.
     * 
     * @param string $function_code
     * @return string
     * @throws \Exception
     */
    public static function installFunction(string $function_code): string {
        // cut off from the left before the beginning of the expression with function parameters
        $i = strpos($function_code, '(');
        if (false === $i) {
            throw new \Exception("Illegal functoin_code");
        }
        // remove left part from function code
        $function_code = substr($function_code, $i);
        
        // right part of function_code will be hashed
        $hash40hex = substr(hash('sha256', $function_code), 0, 40);
        
        // function name is fn_hash40
        $function_name = 'fn_' . $hash40hex;

        // this prefix must exactly match the local-stored code
        $prefix_code = '<' . '?php function ' . $function_name;
        
        // We start creating a file path to save the function code.
        // 1) Make is path of sub-folder
        $local_file = self::$repositories_arr[self::LOCAL_REPO_KEY]  . substr($hash40hex, 0, 2) . DIRECTORY_SEPARATOR;
        
        // 2) Create this sub-folder if not exist
        if (empty(realpath($local_file)) && !mkdir($local_file)) {
            throw new \Exception("Can't create subdir in local-cache: $local_file");
        }
        
        // 3) finish creating the local-file name
        $local_file .= $hash40hex;
        
        // saving function-code to the local_file (with prefix_code in head)
        if (!file_put_contents($local_file, $prefix_code . $function_code)) {
            throw new \Exception("Can't write functoin to local-cache-file: $local_file");
        }
        
        // loading function from saved-file
        require_once $local_file;
        
        // checking for a successful function definition
        if (!function_exists($function_name)) {
            throw new \Exception("Function was not installed, local file was created: $local_file");
        }
        
        // return function name only
        return $function_name;
    }
    
    
    public static function __callStatic(string $name, array $arguments) {
        if (!function_exists($name)) {
            // function is not set, try to load
            if ($err = self::loadFunction($name)) {
                throw new \Exception($err);
            }
        }
        return $name(...$arguments);
    }
    
    
    public function __call(string $name, array $arguments) {
        return self::__callStatic($name, $arguments);
    }
}
