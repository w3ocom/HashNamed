<?php
namespace w3ocom\HashNamed;

class HashNamedRepo {

    /**
     * left part of URL for downloading hashnamed-objects
     * @var array<array<mixed>>
     */
    protected static array $repositories_arr = [];

    /**
     * Key to specify LOCAL-cache-dir in self::$repositories_arr
     */
    public const LOCAL_REPO_KEY = 'LOCAL';
    
    /**
     * Calling HashNamed constructor is easy way to set local_cache_dir
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
                $repo_key = $repo_URL_left;
                $repo_param_arr = [
                    'url' => 1, // 1 means 'url is in repo_key'
                ];
            } else {
                $repo_param_arr = [
                    'url' => $repo_URL_left
                ];
                if ($repo_key === self::LOCAL_REPO_KEY) {
                    $repo_param_arr['is_local'] = true;
                }
            }
            if (empty(self::$repositories_arr[$repo_key])) {
                self::$repositories_arr[$repo_key] = $repo_param_arr;
                $add_cnt++;
            }
        }
        return $add_cnt;
    }
    
    /**
     * Make full-URL from specified hash40hex and specified repository
     * 
     * @param string $repo_key
     * @param string $repo_subdir
     * @param string $hash40hex
     * @param null|array<mixed> $repo_params_arr
     * @return string|null
     */
    public static function getRepoURL(string $repo_key, string $repo_subdir, string $hash40hex, ?array $repo_params_arr = null): ?string {
        $repo_params_arr = $repo_params_arr ?? self::$repositories_arr[$repo_key];

        if (empty($repo_params_arr['url'])) {
            return NULL;
        }

        $repo_URL_left = $repo_params_arr['url'];

        //  1 means url placed in repo_key
        if (1 === $repo_URL_left) {
            $repo_URL_left = $repo_key;
        }

        $full_URL = $repo_URL_left . $repo_subdir . $hash40hex;

        return $full_URL;
    }
    
    /**
     * Return LOCAL-cache-dir or NULL if undefined
     * @return string|null
     */
    public static function getLocalRepo(): ?string {
        return self::$repositories_arr[self::LOCAL_REPO_KEY]['url'] ?? NULL;
    }
    
    /**
     * Is this repository marked as "LOCAL" ?
     *
     * @param string $repo_key
     * @return bool
     */
    public static function isLocalRepo(string $repo_key): bool {
        if ($repo_key === self::LOCAL_REPO_KEY) {
            return true;
        }
        return !empty(self::$repositories_arr[$repo_key]['is_local']);
    }

}
