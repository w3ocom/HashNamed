<?php
/****************************************************************
 * This installer will replace autoloader "vendor/autoload.php" *
 * **************************************************************/
namespace w3ocom\HashNamed;
class AutoLoadInstaller {
/*
 * Parameters:
 *  LOCAL_CACHE_DIR - directory for save downloaded files from repository
 *  NAMESPACE_DIR - directory for storing class_map files, allowing to resolve class-files locations
 */
public const LOCAL_CACHE_DIR = "hashnamed/hashnamed_cache"; // REPLACE TO YOUR VALUE (IF YOU WANT)
public const NAMESPACE_DIR = "hashnamed/namespaces_map";    // REPLACE TO YOUR VALUE (IF YOU WANT)

/*
 * Let's put the code in __construct() and then create self to call it
 */
public function __construct()
{
    // check output directories
    $this->checkDirFromConst("LOCAL_CACHE_DIR");
    $this->checkDirFromConst("NAMESPACE_DIR");

    $this->searchVendorDir("autoload.php", [
        "../../../vendor",
        "../../vendor", 
        "../vendor",
        "vendor",
    ]);

    $this->loadComposerAutoLoadFilesArr("composer/autoload_files.php");

    $this->loadPattern("./autoload.php");
    $this->checkOwnSrcDir("src");
    $this->checkOwnClass('autoloader_class', "AutoLoader.php");
    $this->checkOwnClass('mapper_class', "AutoLoadMapUpdate.php");
    
    $this->installAutoLoader();
}
public function installAutoLoader()
{    
    $autoload_code = $this->prepareCodeFromPattern();
    $this->writeToTestAutoLoadFile($autoload_code);
    require_once $this->test_autoload_file;
    
    $this->createMapForSelf();
    $this->checkAutoLoading(); // autoload HELML-class to check autoload health
    
    $this->createMapForVendorDir();
    // All complete.
    // Lets set autoload.php to vendor-dir
    $this->setAutoLoadFileToVendorDir($autoload_code);
    
    echo "Complete.\n";    
}

/**
 * all FUNCTIONS below
 */

    public $vendor_dir;
    public $old_autoload_file;
    public function searchVendorDir($expected_autoload_php, $expected_vendor_dir_arr) {
        foreach($expected_vendor_dir_arr as $expected_vendor_dir) {
            $this->vendor_dir = $vd = realpath($expected_vendor_dir);
            if ($vd) {
                $this->old_autoload_file = $af = $vd . DIRECTORY_SEPARATOR . $expected_autoload_php;
                if ($af) break;
            }
        }
        if (!$vd)
            die("Not found vendor dir, expected place: $expected_vendor_dir \n");

        if (!$af)
            die("Not found $expected_autoload_php in $vd \n");
    }

    public $autoload_files_arr = [];
    public $autoload_files_code = "//No autoload_files\n";
    public function loadComposerAutoLoadFilesArr($autoload_files_add_path) {
        $file_name = dtr($this->vendor_dir) . '/' . $autoload_files_add_path;
        if (is_file($file_name)) {
            // load array from file
            $this->autoload_files_arr = $arr = (include $file_name);
            if (!is_array($arr)) {
                die("File compose/autoload.php is damaged: $file_name \n");
            }
            if (!empty($arr)) {
                // prepare code to set instead //[AUTOLOAD_FILES]
                $code = ['//from composer/autoload_files.php -- ' . $file_name . "\n"];
                foreach($arr as $helper_file) {
                    $helper_file = dtr($helper_file);
                    $code[] = "require_once '$helper_file';";
                }
                $this->autoload_files_code = implode("\n", $code);
            }
        } else {
            echo "WARNING: composer/autoload.php not found: $file_name \n";
        }
    }
    
    public $self_src_dir;
    public function checkOwnSrcDir($self_src_dir) {
        $this->self_src_dir = $d = realpath($self_src_dir);
        if (!$d)
            die("Not found self-src directory: $self_src_dir");
    }
    
    
    public $pattern_autoload_file;
    public function loadPattern($autoload_pattern) {
        $this->pattern_autoload_file = $af = realpath($autoload_pattern);
        if (!$af)
            die("Not found pattern fro autoload.php \n");
        
    }

    public $autoloader_class;
    public $mapper_class;
    public function checkOwnClass($class_val_name, $class_path) {
        $expected_path = $this->self_src_dir . DIRECTORY_SEPARATOR . $class_path;
        $this->$class_val_name = $af = realpath($expected_path);
        if (!$af)
            die("Not found $class_val_name in $class_path \n");
    }

    public $local_cache_dir;
    public $namespace_dir;
    public function checkDirFromConst($const_name) {
        $path = constant(__CLASS__ . "::$const_name");
        if (!$path) die("Not found constatn $const_name");
        if (!is_dir($path) && !mkdir($path, 0777, true))
            die("Can't found or create directory $const_name = $path");
        
        $path = realpath($path);
        if (!$path) die("Unexpected result");
        
        $name = strtolower($const_name);

        $this->$name = $path;
    }

    public function prepareCodeFromPattern() {
        $autoload_pattern = file_get_contents($this->pattern_autoload_file);
        if (!$autoload_pattern)
            die("Can't load autoload.php - pattern from: " . $this->pattern_autoload_file);
        
        $replaces_arr = [
            "src/AutoLoader.php" => dtr($this->autoloader_class),
            "hashnamed/hashnamed_cache" => dtr($this->local_cache_dir),
            "hashnamed/namespaces_map" => dtr($this->namespace_dir),
            '//[AUTOLOAD_FILES]' => $this->autoload_files_code,
        ];
        
        $count = 0;
        $result_code = str_replace(
            array_keys($replaces_arr), // search
            array_values($replaces_arr), // replaces
            $autoload_pattern, // source
            $count
        );
        if (count($replaces_arr) !== $count)
            die("Incomplete replaces");

        return $result_code;
    }
    
    public $test_autoload_file;
    public function writeToTestAutoLoadFile($code) {
        $this->test_autoload_file = $file_name = $this->local_cache_dir . DIRECTORY_SEPARATOR . 'autoload.php';
        $wb = file_put_contents($file_name, $code);
        if ($wb !== strlen($code))
            die ("Error write test-autoload-file: $file_name \n");
    }
    
    public function setAutoLoadFileToVendorDir($autoload_code) {
        $a_name = $this->old_autoload_file;
        if (is_file($a_name)) {
            // compare old code with this code
            $old_code = file_get_contents($a_name);
            if ($old_code === $autoload_code) return; // no need to write new code
            
            $rename_old_autoload_to = $this->vendor_dir . DIRECTORY_SEPARATOR . 'autoload_' . date("Y-m-d_H-i-s") . '.php';
            if (!rename($a_name, $rename_old_autoload_to))
                die("Can't rename old-autoload file " . $a_name . " to $rename_old_autoload_to");
        }
        $wb = file_put_contents($a_name, $autoload_code);
        if ($wb !== strlen($autoload_code))
            die ("Error write new autoload.php: $file_name \n");        
    }
    
    public $mapper_obj;
    public function createMapForSelf() {
        require_once $this->mapper_class;
        $this->mapper_obj = new AutoLoadMapUpdate($this->self_src_dir);
    }
    
    public function createMapForVendorDir() {
        $this->mapper_obj->addDir(dirname($this->vendor_dir));
    }
    
    public function checkAutoLoading() {
        // change 'require_once' to 'include'
        AutoLoader::$use_require_once = false;
        // test autoloading this class:
        $h = new HELML();
        $x = $h->toHELML(['a'=>1]);
        if ($x !== "a: 1\n")
            die("Unexpected result by testing autoloaded HELML class");
    }
} // RUN
if (!function_exists('dtr')) {
    function dtr($str) {
        return strtr($str, '\\', '/');
    }
}
new AutoLoadInstaller();
