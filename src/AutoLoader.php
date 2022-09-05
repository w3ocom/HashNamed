<?php
namespace w3ocom\HashNamed;

class AutoLoader
{
    /**
     * Path where the hashnamed-files are located
     * @var string
     */
    public static string $hashnamed_cache_dir;

    /**
     * Base directory for namespace-definition
     * each subfolder named as namespace and may contain class_map.php file
     *  each file class_map.php must return an array of the following structure:
     *   return [
     *    'full\class\name1' => 'path_to_file1',
     *    'full\class\name2' => 'path_to_file2',
     *    ...
     *   ];
     * @var string
     */
    public static string $namespaces_dir;

    /**
     * Elements contain integer values for those namespaces that are checked
     *  [namespace] => int value 0,1 or 2
     *    0 - folder not exist
     *    1 - folder exist, but have not class_map.php
     *    2 - folder exist and contain class_map.php
     * 
     * @var array<int>
     */
    public static array $checked_namespaces_arr = [];
    
    public const NS_FOLDER_NOT_EXIST = 0;
    public const NS_FOLDER_EXIST = 1;
    public const NS_FOLDER_HAVE_FILE = 2;
    
    public static bool $use_require_once = true;
    /**
     * Elements contain:
     *  string path to php-class-file
     *  string with 42-bytes of C_hashnamed-name (for hashnamed-class)
     * 
     * @var array<string>
     */
    protected static array $class_to_path_arr = [];

    public static function autoLoadRegisrer(): void {
        spl_autoload_register([__CLASS__, 'autoLoad'], true, true);
    }
    
    public function __construct(string $local_cache_dir, ?string $name_space_dir = null)
    {
        self::setLocalCacheDir($local_cache_dir);

        if ($name_space_dir) {
            self::setNameSpaceDir($name_space_dir);
        }
        
        self::autoLoadRegisrer();
    }
    public static function setLocalCacheDir(string $local_cache_dir): void {
        self::$hashnamed_cache_dir = realpath($local_cache_dir);
        if (empty(self::$hashnamed_cache_dir)) {
            throw new \Exception("Not found local_cache_dir: $local_cache_dir");
        }
    }
    
    public static function setNameSpaceDir(string $namespaces_dir): void {
        self::$namespaces_dir = realpath($namespaces_dir);
        if (empty(self::$namespaces_dir)) {
            throw new \Exception("Not found namespaces_dir: $namespaces_dir");
        }
    }
    
    public static function calcFileNameFromHash(string $hashnamed_name, ?string $base_dir = null): ?string {
        if (!$base_dir) {
            if (empty(self::$hashnamed_cache_dir)) {
                throw new \Exception("Source-dir not specified");
            }
            $base_dir = self::$hashnamed_cache_dir;
        }
        
        // verification hashnamed_name
        $hash40hex = substr($hashnamed_name, -40);
        if (strlen($hash40hex) === 40 && ctype_xdigit($hash40hex)) {
            return $base_dir . DIRECTORY_SEPARATOR . substr($hash40hex, 0, 2) . DIRECTORY_SEPARATOR . $hash40hex;
        }
        return NULL;        
    }
    
    public static function autoLoad(string $class): ?bool {
        $path_from_map = self::$class_to_path_arr[$class] ?? null;
        
        if (!$path_from_map) {
            // No in clas-map, may be it is a hash
            $i = strrpos($class, '\\');
            if (strlen($class) - $i > 40) {
                $local_file = self::calcFileNameFromHash($class);
                if ($local_file && is_file($local_file)) {
                    require_once $local_file;

                    if (!class_exists($class, false)) {
                        // maybe class defined with differen name? try detect real class_name from code!
                        $class_def_arr = self::detectClassDefinition($local_file);
                        if ($class_def_arr) {
                            $class_name = $class_def_arr['class_name'];
                            if ($class_name !== $class) {
                                class_alias($class_name, $class);
                            }
                        }
                    }

                    return true;
                }
            }
            // if class is not hash, or not found, try loading namespaces
            if (self::loadNameSpaceMap($class)) {
                // if $class_to_path_arr was changed
                $path_from_map = self::$class_to_path_arr[$class] ?? null;
            }
        }

        if ($path_from_map) {
            if (strlen($path_from_map) === 42 && $path_from_map[1] == '_') {
                $path_from_map = self::calcFileNameFromHash($path_from_map);
            }
            if (is_file($path_from_map)) {
                require_once $path_from_map;

                return true;
            }
        }

        return NULL;
    }
    
    public static function loadNameSpaceMap(string $class): bool {
        $changed = false;
        $base_path = self::$namespaces_dir;
        if ($base_path) {
            // search all unchecked namespaces
            $unckecked_ns_arr = [];
            $ost_ns = $class;
            do {
                $i = strrpos($ost_ns, '\\');
                $ost_ns = $i ? substr($ost_ns, 0, $i) : '\\';
                // if the namespace is checked, then also previous namespaces are already checked
                if (isset(self::$checked_namespaces_arr[$ost_ns])) break;
                $unckecked_ns_arr[] = $ost_ns;
            } while ($i);
            
            // try to load all unknown namespaces
            $curr_ns = end($unckecked_ns_arr);
            while ($curr_ns) {
                if ($base_path) {
                    // calculate path for this namespace
                    $chk_path_add = strtr($curr_ns, '\\', DIRECTORY_SEPARATOR);
                    if (substr($curr_ns, 0, 1) !== '\\') {
                        $chk_path_add = DIRECTORY_SEPARATOR . $chk_path_add;
                    }               
                    $chk_path = $base_path . $chk_path_add;
                    
                    if (is_dir($chk_path)) {
                        self::$checked_namespaces_arr[$curr_ns] = self::NS_FOLDER_EXIST;
                        // dir found, check class_map file
                        $class_map_file = $chk_path . DIRECTORY_SEPARATOR . 'class_map.php';
                        if (is_file($class_map_file)) {
                            self::$checked_namespaces_arr[$curr_ns] = self::NS_FOLDER_HAVE_FILE;
                            // will return true if already included, return array if it's loaded now
                            if (self::$use_require_once) {
                                $class_to_path_arr = (require_once $class_map_file);
                            } else {
                                $class_to_path_arr = (include $class_map_file);
                            }
                            if (is_array($class_to_path_arr)) {
                                self::$class_to_path_arr = array_merge(self::$class_to_path_arr, $class_to_path_arr);
                                $changed = true;
                                // load files from key '0'
                                if (!empty($class_to_path_arr['0']) && is_array($class_to_path_arr['0'])) {
                                    foreach($class_to_path_arr['0'] as $file_req_once) {
                                        require_once $file_req_once;
                                    }
                                }
                            }
                        }
                    } else {
                        // set empty path to skip next path check
                        $base_path = '';
                    }
                }
                if (!isset(self::$checked_namespaces_arr[$curr_ns])) {
                    self::$checked_namespaces_arr[$curr_ns] = self::NS_FOLDER_NOT_EXIST;
                }

                $curr_ns = prev($unckecked_ns_arr);
            }
        }
        return $changed;
    }
    
    public static function detectClassDefinition(string $local_file, int $load_length = 4096, int $next_length = 0): ?array {
        // get header from code, first 2Kb
        $code_header = file_get_contents($local_file, false, null, 0, $load_length);
        if (empty($code_header)) {
            return NULL;
        }
        // php-tag required
        if (substr($code_header, 0, 5) !== '<'.'?php') {
            return NULL;
        }
        
        // detect class-name
        if (
            preg_match("/
    (?'type'class|interface|trait)[\s]+
    (?'name'[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)[\s]*
    (?'middle'[\\\\a-zA-Z0-9\\,_\x80-\xff\s]*)[\s]*
    (\\{)/ix", $code_header, $matches)
        ) {
            $php_type = 'php-' . $matches['type'];
            $class_name = $short_name = $matches['name'];
            $class_add_def = $matches['middle'];

            //detect namespace
            if (preg_match("/namespace[\s]+([a-zA-Z_\x80-\xff][a-zA-Z0-9\\\\_\x80-\xff]*)[\s]*[;]/", $code_header, $matches)) {
                $namespace = trim($matches[1]);
                $class_name = $namespace . '\\' . $short_name;
            } else {
                $namespace = '\\';
            }
            
            return compact('namespace', 'class_name', 'short_name', 'php_type');
        } elseif ($next_length) {
            return self::detectClassDefinition($local_file, $next_length, 0);
        }
        
        return NULL;
    }
    
    public static function convertNsToDir(string $namespace, ?string $base_path = null): string {
        $ns_folder = $base_path ?? self::$namespaces_dir;

        $path_add = strtr($namespace, '\\', DIRECTORY_SEPARATOR);
        if (substr($namespace, 0, 1) !== '\\') {
            $ns_folder .= DIRECTORY_SEPARATOR;
        } elseif (strlen($namespace) === 1) {
            $path_add = '';
        }
        return $ns_folder . $path_add;
    }
}
