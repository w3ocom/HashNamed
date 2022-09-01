<?php
namespace w3ocom\HashNamed;

class HashNamedCore extends HashNamedRepo {
    
    /**
     * Store call_names for each loaded hashnamed-obj
     *  [hash40hex] => call_name
     * this is necessary because call_name can be different and contain namespace
     *
     * @var array<string>
     */
    protected static array $hashnamed_call_name_arr = [];
    
    public static bool $accept_remote_renamed = true;
    
    /**
     * Prefixes for function and class names
     * @var array<string>
     */
    protected static array $type_prefix = [
        'php-class' => 'C_',
        'php-function' => 'fn_',
    ];

    /**
     * Store h_arr for hashnamed-obj when local-file is included
     * @var array<array<string>>
     */
    protected static array $loaded_hashnamed_arr = [];
    
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
     * @param bool $save_hashnamed
     * @param string $expected_type
     * @return null|array<string>
     * @throws \Exception
     */
    public static function loadHashNamedCode(string $hash40hex, bool $save_hashnamed = true, ?string $expected_type = null): ?array {

        // arguments verification
        if (strlen($hash40hex) !== 40) {
            throw new \Exception("Invalid hash40hex length");
        }
        
        if (empty(self::getLocalRepo())) {
            throw new \Exception("HashNamed LOCAL-cache-dir MUST specified before");
        }

        $repo_subdir = substr($hash40hex, 0, 2) . '/';
        
        foreach(self::$repositories_arr as $repo_key => $parameters) {
            $full_URL = self::getRepoURL($repo_key, $repo_subdir, $hash40hex, $parameters);
            if (!$full_URL) continue;
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
            
            // compare hash declared in the header with the requested hash
            if (substr($h_arr['hash'], 0, 40) !== $hash40hex) continue;
            
            // check 'type', skip unknown types
            if (empty(self::$type_prefix[$h_arr['type']])) continue; // undefined type

            // compare type declared in the header with the expected type
            if ($expected_type && ($expected_type !== $h_arr['type'])) {
                // if this code is not from local repository - skip it
                if ($repo_key !== self::LOCAL_REPO_KEY) continue;
                // if this code found in local-cache-dir with unexpected type
                throw new \Exception("Hashnamed $hash40hex was found, but it has an unexpected type:" . $h_arr['type']);
            }
            
            // make hashnamed_name from type-prefix and hash40hex
            $hashnamed_name = self::$type_prefix[$h_arr['type']] . $hash40hex;
            
            // check 'renamed'
            if ($is_hashnamed = !empty($h_arr['renamed'])) {
                // if renamed is set, it may content alternative hashnamed-name, but this name must contain hash40hex
                if (false !== strpos($h_arr['renamed'], $hash40hex)) {
                    // if renamed contains hash40hex - accept this name
                    $hashnamed_name = $h_arr['renamed'];
                }
                // if code contain hashnamed names, we need to rename back for verification
                if (($repo_key !== self::LOCAL_REPO_KEY) && !self::$accept_remote_renamed) {
                    // skip it because we won't accept renamed-code from remote repositories
                    // to change this behavior, you may set self::$accept_remote_renamed = true
                    continue;
                }
            }

            // cut body-data from hashnamed-object
            $data_for_hash = \substr($data_src, $h_arr['_h'][0]);
            
            if ($is_hashnamed) {
                // rename back: replace all hashnamed-names to real-name
                $data_for_hash = str_replace($hashnamed_name, $h_arr['name'], $data_for_hash);
            }
            
            // calculate hash from body
            $hash_of_body = hash('sha256', $data_for_hash);

            // hash verification
            if (substr($hash_of_body, 0, 40) !== $hash40hex) continue;

            // GOOD! Hash is equal

            $need_save_to_local_file = true;

            if ($repo_key === self::LOCAL_REPO_KEY) {
                // data received from local-cache
                if ($is_hashnamed === $save_hashnamed) {
                    // if already named as need
                    $need_save_to_local_file = false;
                }
            }

            // subfolder for save local_file
            $local_file_sub = self::getLocalRepo() . $repo_subdir;
            // make full local_file name from subfolder and hash40hex
            $local_file = $local_file_sub . $hash40hex;

            if ($need_save_to_local_file) {
                // before save local_file check local-cache-dir and create if need
                if (empty(realpath($local_file_sub)) && !mkdir($local_file_sub)) {
                    throw new \Exception("Can't create sub-dir for storage data: $local_file_sub");
                }

                if ($save_hashnamed) {
                    $h_arr['renamed'] = $hashnamed_name;
                    // rename from real_name to hashnamed_name
                    $data_for_write = str_replace($h_arr['name'], $hashnamed_name, $data_for_hash);
                } else {
                    // do not rename, save canonical code
                    $data_for_write = $data_for_hash;
                    $h_arr['renamed'] = null;
                }

                // make prefix with h_arr in HELML-header
                $prefix = '<'."?php\n/*\n" . HELML::ToHELML($h_arr) . "*/\n\n";

                // save prefix and data
                if (!file_put_contents($local_file, $prefix . $data_for_write)) {
                    throw new \Exception("Can't save received data to local-cache-dir");
                }
            }

            // get namespace
            $namespace = $h_arr['namespace'] ?? '';

            // what name can be used to call this object
            $call_name = $save_hashnamed ? $hashnamed_name : $h_arr['name'];
            $call_name = $namespace . '\\' . $call_name;

            self::$hashnamed_call_name_arr[$hash40hex] = $call_name;

            $h_arr['call_name'] = $call_name;
            $h_arr['hashnamed_name'] = $hashnamed_name;
            $h_arr['local_file'] = $local_file;

            // Success
            return $h_arr;
        }
        // NOT FOUND
        return NULL;
    }
}
