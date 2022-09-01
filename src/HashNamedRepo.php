<?php
namespace w3ocom\HashNamed;

class HashNamedRepo {

    /**
     * left part of URL for downloading hashnamed-objects
     * @var array<mixed>
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
                $repo_key = $repo_URL_left;
                $repo_URL_left = true;
            }
            if (empty(self::$repositories_arr[$repo_key])) {
                self::$repositories_arr[$repo_key] = $repo_URL_left;
                $add_cnt++;
            }
        }
        return $add_cnt;
    }
    
    public static function getRepoURL(string $repo_key, $parameters = null): ?string {
        $repo_URL_left = $parameters ?? self::$repositories_arr[$repo_key];
        if (true === $repo_URL_left) {
            $repo_URL_left = $repo_key;
        } elseif (empty($parameters)) {
            return NULL;
        } elseif (!is_string($repo_URL_left)) {
            throw new \Exception("Unsupported repository parameters");
        }
        return $repo_URL_left;
    }
}
