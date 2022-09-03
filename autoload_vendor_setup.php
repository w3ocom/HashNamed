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
public function __construct() {
    
    //$this->tryToFindVendorDir("../vendor", "autoload.php");
    $this->tryToFindVendorDir("vendor", "autoload.php");
    $this->tryToFindAutoLoadPattern("./autoload.php");
    $this->tryToFindMySelfSrcDir("src");
    $this->tryToFindMySelfClass('autoloader_class', "AutoLoader.php");
    $this->tryToFindMySelfClass('mapper_class', "AutoLoadMapUpdate.php");
    $this->checkDirFromConst("LOCAL_CACHE_DIR");
    $this->checkDirFromConst("NAMESPACE_DIR");
    
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
    public function tryToFindVendorDir($expected_vendor_dir, $expected_autoload_php) {
        $this->vendor_dir = $vd = realpath($expected_vendor_dir);
        if (!$vd)
            die("Not found vendor dir, expected place: $expected_vendor_dir \n");

        $this->old_autoload_file = $af = $vd . DIRECTORY_SEPARATOR . $expected_autoload_php;
        if (!$af)
            die("Not found $expected_autoload_php in $vd \n");
    }
    
    public $self_src_dir;
    public function tryToFindMySelfSrcDir($self_src_dir) {
        $this->self_src_dir = $d = realpath($self_src_dir);
        if (!$d)
            die("Not found self-src directory: $self_src_dir");
    }
    
    
    public $pattern_autoload_file;
    public function tryToFindAutoLoadPattern($autoload_pattern) {
        $this->pattern_autoload_file = $af = realpath($autoload_pattern);
        if (!$af)
            die("Not found pattern fro autoload.php \n");
        
    }

    public $autoloader_class;
    public $mapper_class;
    public function tryToFindMySelfClass($class_val_name, $class_path) {
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
            "src/AutoLoader.php" => $this->autoloader_class,
            "hashnamed/hashnamed_cache" => $this->local_cache_dir,
            "hashnamed/namespaces_map" => $this->namespace_dir
        ];
        
        // replace \ to doblue \\  (for Windows path)
        foreach($replaces_arr as $k => $v) {
            $replaces_arr[$k] = str_replace('\\', '\\\\', $v);
        }
        
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
        $this->mapper_obj->addDir($this->vendor_dir);
    }
    
    public function checkAutoLoading() {
        // test autoloading this class:
        $h = new HELML();
        $x = $h->toHELML(['a'=>1]);
        if ($x !== "a: 1\n")
            die("Unexpected result by testing autoloaded HELML class");
    }
} // RUN
new AutoLoadInstaller();
