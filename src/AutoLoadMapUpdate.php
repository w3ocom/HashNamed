<?php
namespace w3ocom\HashNamed;

class AutoLoadMapUpdate {
    public string $namespaces_dir;
    
    public function __construct(?string $source_dir_or_file = null, ?string $namespaces_dir = null) {
        
        $namespaces_dir = $namespaces_dir ?? AutoLoader::$namespaces_dir;
        if (!$namespaces_dir) {
            throw new \Exception("Undefined namespaces_dir");
        }
        $this->namespaces_dir = realpath($namespaces_dir);
        if (!$this->namespaces_dir || !is_dir($this->namespaces_dir)) {
            throw new \Exception("namespace_dir not found: $namespaces_dir");
        }
        
        if ($source_dir_or_file) {
            $err = $this->addPath($source_dir_or_file);
        }
    }

    public function addPath(string $source_dir_or_file): ?string {
        $source_path = realpath($source_dir_or_file);
        if (!$source_path) {
            return "Not found source path: $source_dir_or_file";
        }

        if (is_dir($source_path)) {
            $err = $this->addDir($source_path);
        } else {
            $err = $this->addFile($source_path);
        }
        if ($err) {
            return "Can't add $source_path : $err";
        }

        // Successful
        return NULL;
    }
    
    public function addDir(string $dir_path): ?string {
        if (!is_dir($dir_path)) {
            return "It is not directory path: $dir_path";
        }
        $s = scandir($dir_path);
        foreach($s as $path) {
            if (substr($path, 0, 1) === '.') continue;
            $full_path = $dir_path . DIRECTORY_SEPARATOR . $path;
            if (is_file($full_path)) {
                $this->addFile($full_path);
            } else {
                $this->addDir($full_path);
            }
        }
        return NULL;
    }
    
    public function addFile(string $class_path): ?string {
        $class_def_arr = AutoLoader::detectClassDefinition($class_path);
        if (!$class_def_arr) {
            return "Can't find class-header in file: $class_path";
        }

        $class_name = $class_def_arr['class_name'];
        
        $ds_arr = $this->scanDeclarationSpaces($class_name);
        
        $where_is_this_class_declared = $ds_arr['where_is_this_class_declared'];
        $where_class_may_be_declared = $ds_arr['where_class_may_be_declared'];
        
        $err = null;
        
        $already_declared = false;
        foreach($where_is_this_class_declared as $class_map_file => $path_in_file) {
            $set_class_path = $already_declared ? NULL : $class_path;
            $err = $this->setClassMap($class_map_file, $class_name, $set_class_path);
            if (!$err) {
                $already_declared = true;
            }
        }
        
        if (!$already_declared) {
            if (!$where_class_may_be_declared) {
                throw new \Exception("Unexpected situation");
            }
            $err = $this->setClassMap($where_class_may_be_declared, $class_name, $class_path);
        }

        return $err;
    }
    
    public static function removeEmptyDirUp(string $dir_path): bool {
        $s = scandir($dir_path);
        if (count($s) !== 2) return false;
        $res = rmdir($dir_path);
        self::removeEmptyDirUp(dirname($dir_path));
        return $res;
    }
    
    public function setClassMap(string $class_map_file, string $class_name, ?string $class_path): ?string {
        $changed = true;
        if (is_file($class_map_file)) {
            $arr = (include $class_map_file);
            if (!$class_path && isset($arr[$class_name])) {
                unset($arr[$class_name]);
                if (empty($arr)) {
                    unlink($class_map_file);
                    self::removeEmptyDirUp(dirname($class_map_file));

                    // clear Autoload-checked_namespaces because there are changed
                    AutoLoader::$checked_namespaces_arr = [];
                    // Success;
                    return NULL;
                }
            } else {
                if (!is_array($arr)) {
                    $arr = [];
                }
                if (isset($arr[$class_name]) && ($arr[$class_name] === $class_path)) {
                    $changed = false;
                } else {
                    $arr[$class_name] = $class_path;
                }
            }
        } else {
            // create new array
            $arr = [$class_name => $class_path];
            // is file not found - need check file directory
            $dir_path = dirname($class_map_file);
            if (!is_dir($dir_path) && !mkdir($dir_path, 0777, true)) {
                throw new \Exception("Can't create path $dir_path");
            }
        }
        
        if ($changed) {
            // clear Autoload-checked_namespaces because there are changed
            AutoLoader::$checked_namespaces_arr = [];
            
            // write $arr to $class_map_file
            $fp = fopen($class_map_file, 'wb');
            if (!$fp) {
                throw new \Exception("Can't open file for write: $class_map_file");
            }
            while ($fp) {
                if (!fwrite($fp, '<' . "?php\nreturn [\n")) break;
                foreach($arr as $cls => $path) {
                    if (!fwrite($fp, "\t'$cls' => '$path',\n")) break 2;
                }
                if (!fwrite($fp, "];\n")) break;
                fclose($fp);
                $fp = null;
            }
            if ($fp) {
                fclose($fp);
                return "Can't write data to file: $class_map_file";
            }
        }
        // Success
        return NULL;
    }
    
    public function scanDeclarationSpaces(string $class_name): array {

        $ns_arr = $this->classNameSpacesToArr($class_name, true);
        
        $where_is_this_class_declared = [];
        $where_class_may_be_declared = '';
        // walk all declaration files
        foreach($this->walkNameSpaceDeclarationFiles($ns_arr, false) as $curr_ns => $class_map_file) {
            if (is_file($class_map_file)) {
                // load declarations array
                $class_to_path_arr = (include $class_map_file);
                if (isset($class_to_path_arr[$class_name])) {
                    $where_is_this_class_declared[$class_map_file] = $class_to_path_arr[$class_name];
                }
                if (empty($where_is_this_class_declared) && (strlen($curr_ns) > 1)) {
                    $where_class_may_be_declared = $class_map_file;
                }
            }
        }
        if (empty($where_is_this_class_declared) && empty($where_class_may_be_declared)) {
            $where_class_may_be_declared = $class_map_file;
        }
        return compact('where_is_this_class_declared', 'where_class_may_be_declared');
    }
    
    /**
     * Convert full_class_name (namespace\class) to namespaces array
     * 
     * @param string $class_name
     * @param bool $reverse
     * @return array<string>
     */
    public function classNameSpacesToArr(string $class_name, bool $reverse = false): array {
        $arr = [];
        $ost_ns = $class_name;
        do {
            $i = strrpos($ost_ns, '\\');
            $ost_ns = $i ? substr($ost_ns, 0, $i) : '\\';
            $arr[] = $ost_ns;
        } while ($i);
        
        if ($reverse) {
            $arr = array_reverse($arr);
        }
        return $arr;
    }
    
    /**
     * Generator: Walk by namespace-declaration-folders
     * 
     * @param array<string> $namespaces_arr
     * @param bool $only_existing
     * @return \Generator
     */
    public function walkNameSpaceFolders(array $namespaces_arr, bool $only_existing = true): \Generator {
        $base_path = $this->namespaces_dir;
        
        foreach($namespaces_arr as $curr_ns) {
            // calculate path for this namespace
            $ns_folder = AutoLoader::convertNsToDir($curr_ns, $base_path);
            if (!$only_existing || is_dir($ns_folder)) {
                yield $curr_ns => $ns_folder;
            }
        }        
    }
    
    /**
     * Generator: Walk by namespace-declaration-files
     * 
     * @param array<string> $namespaces_arr
     * @param bool $only_existing
     * @return \Generator
     */
    public function walkNameSpaceDeclarationFiles(array $namespaces_arr, bool $only_existing = true): \Generator {
        foreach($this->walkNameSpaceFolders($namespaces_arr, false) as $curr_ns => $ns_folder) {
            $class_map_file = $ns_folder . DIRECTORY_SEPARATOR . 'class_map.php';
            if (!$only_existing || is_file($class_map_file)) {
                yield $curr_ns => $class_map_file;
            }
        }
    }
}
