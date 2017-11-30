<?php
/**
 * @package zesk
 * @subpackage webcache
 * @author kent
 * @copyright &copy; 2017 Market Acumen, Inc.
 */
namespace zesk\WebCache\Local;

use zesk\Application;
use zesk\Directory;
use zesk\Timestamp;
use zesk\URL;
use zesk\Cache;
use zesk\Exception_File_Permission;
use zesk\Exception_NotFound;
use zesk\Net_HTTP_Client;
use zesk\File;
use zesk\Net_HTTP;

/**
 * @todo should probably refactor this such that we separate the retrieval from the storage
 * e.g. separation of concerns
 *  
 * @author kent
 *
 */
class Instance extends \zesk\WebCache\Instance {
	/**
	 * Directory where cache files are stored
	 * @var string
	 */
	private $cache_dir = null;
	
	/**
	 *
	 * @var double
	 */
	private $last_request = null;
	
	/**
	 * 
	 * @param Application $application
	 */
	public function initialize() {
		if ($this->cache_dir !== null) {
			return $this->cache_dir;
		}
		$path = $this->option("path");
		if (!$path) {
			$path = $this->default_path();
		}
		$this->cache_dir = $path;
		Directory::depend($this->cache_dir, 0770);
	}
	
	/**
	 *
	 * @param unknown $hash
	 * @param string $create
	 * @return string
	 */
	public function binary_file_path($hash, $create = true) {
		$dir = path($this->cache_dir, "bits", substr($hash, 0, 1));
		if ($create) {
			Directory::depend($dir, 0770);
		}
		return path($dir, $hash);
	}
	
	/**
	 * Get or Set settings for a URL
	 * @param string $url URL to get/set settings for
	 * @param array $value NULL to get, array to set
	 * @throws Exception_File_Permission
	 * @return Info
	 */
	public function settings_path($hash, $create = false) {
		$db_dir = path($this->cache_dir, 'db', substr($hash, 0, 1));
		if ($create) {
			Directory::depend($db_dir, 0770);
		}
		return path($db_dir, $hash . ".settings");
	}
	
	/**
	 * 
	 * @param unknown $url
	 * @param Info $value
	 * @throws Exception_File_Permission
	 * @return Info
	 */
	private function settings($url, Info $value = null) {
		$url_hash = md5($url);
		$db_file = $this->settings_path($url_hash, $value !== null);
		$mode = "r+";
		$is_new = false;
		if (!is_file($db_file)) {
			if ($value === null) {
				throw new Exception_NotFound("Cache not found for {url}", array(
					"url" => $url
				));
			}
			$is_new = true;
			$mode = "w+";
		}
		$lock = fopen($db_file, $is_new ? "w" : "r+");
		if (!$lock) {
			throw new Exception_File_Permission($db_file, "fopen with mode $mode");
		}
		if (!flock($lock, LOCK_EX)) {
			fclose($lock);
			throw new Exception_File_Permission("Can not open $db_file exclusively");
		}
		if ($is_new) {
			$settings = array();
		} else {
			fseek($lock, 0);
			$data = "";
			while (!feof($lock)) {
				$data .= fread($lock, 10240);
			}
			$settings = $data ? unserialize($data) : null;
			if (!is_array($settings)) {
				$settings = array();
			}
		}
		if ($value === null) {
			fclose($lock);
			return Info::factory(Info::class, $settings);
		}
		fseek($lock, 0);
		ftruncate($lock, 0);
		fwrite($lock, serialize($value->to_array()));
		fclose($lock);
		return $value;
	}
	
	/**
	 * Download a remote URL
	 * @param string $url URL to download
	 * @return array of remote file name, temp path name
	 */
	private function _fetch_url($url, array $options = array()) {
		$request_interval_milliseconds = avalue($options, "request_interval_milliseconds");
		if ($request_interval_milliseconds > 0 && $this->last_request !== null) {
			$wait_minimum = $request_interval_milliseconds / 1000.0;
			$now = microtime(true);
			$delta = $now - $this->last_request;
			if ($delta < $wait_minimum) {
				$wait_for = $wait_minimum - $delta;
				$microseconds = intval($wait_for * 1000000);
				$this->application->logger->notice("Sleeping for $wait_for seconds ...");
				usleep($microseconds);
			}
		}
		$client = new Net_HTTP_Client($this->application, $url);
		$timeout = avalue($options, "timeout");
		if ($timeout) {
			$client->timeout($timeout);
		}
		$temp_file_name = File::temporary();
		$client->follow_location(true);
		$client->user_agent(avalue($options, "user_agent", 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:12.0) Gecko/20100101 Firefox/12.0'));
		$client->destination($temp_file_name);
		$this->application->logger->notice("Downloading $url");
		$client->go();
		$this->last_request = microtime(true);
		return array(
			$client->filename(),
			$temp_file_name,
			$client->response_code()
		);
	}
	
	/**
	 * Retrieve settings for URL
	 * @param string $url URL to retrieve settings
	 * 
	 * @return Info
	 */
	public function info($url) {
		$url = URL::normalize($url);
		$this->_init();
		$url_hash = md5($url);
		return $this->settings($url);
	}
	
	/**
	 * @return \Iterator
	 */
	public function iterator() {
		$this->_init();
		if (!is_dir($this->cache_dir)) {
			return array();
		}
		return new Iterator($this->cache_dir);
	}
	public function url($url, array $options = array()) {
		$url = URL::normalize($url);
		$this->_init();
		
		$binary_dir = path($this->cache_dir, "bits");
		Directory::depend($binary_dir, 0770);
		
		$settings = self::settings($url);
		$hash = $settings->hash;
		$name = $settings->name;
		$ttl = $settings->ttl;
		
		$now = Timestamp::now("UTC");
		
		$ttl = intval(avalue($options, "ttl", $ttl));
		if ($ttl <= 0) {
			$ttl = $this->default_ttl();
		}
		$expire = $now + $ttl;
		
		$need_check = $settings->created === null || $name === null || $hash === null;
		if (!$need_check && $hash !== null) {
			$binary_file = $this->binary_file_path($hash, false);
			$need_check = !file_exists($binary_file);
			if (!$need_check) {
				$need_check = filesize($binary_file) !== $settings->size;
			}
		}
		if (!$need_check && $now > $settings->checked + $ttl) {
			$this->application->logger->debug("File_Cache_Local::_url({url}) TTL expired ({now} > {expire})", compact("now", "expire", "url"));
			$need_check = true;
		}
		if ($need_check) {
			list($name, $temp_file, $new_response_code) = $this->_fetch_url($url, $options);
			$new_hash = md5_file($temp_file);
			$settings->checked = time();
			if ($hash !== $new_hash || $settings->response_code !== $new_response_code) {
				if (!empty($hash)) {
					$binary_file = $this->binary_file_path($hash, false);
					if (file_exists($binary_file)) {
						unlink($binary_file);
					}
				}
				$binary_file = $this->binary_file_path($new_hash, true);
				$settings->ttl = $ttl;
				$settings->hash = $new_hash;
				$settings->size = filesize($temp_file);
				$settings->status = intval($new_response_code) === Net_HTTP::Status_OK ? true : false;
				$settings->created = $now;
				$settings->response_code = $new_response_code;
				$settings->name = $name;
				$settings->url = $url;
				rename($temp_file, $binary_file);
			} else {
				unlink($temp_file);
			}
			self::settings($url, $settings);
		}
		$binary_file = $this->binary_file_path($settings['hash']);
		if (!$settings['status']) {
			throw new Exception_NotFound("{url} not found {response_code}", $settings);
		}
		return array(
			$binary_file,
			$settings->name
		);
	}
	
	/**
	 *
	 * @return string
	 */
	private function default_path() {
		$code = $this->option("code", "default");
		$code = File::clean_path($code);
		return $this->application->path('cache/webcache/' . $code);
	}
	
	/**
	 * 
	 * @return integer
	 */
	private function default_ttl() {
		return to_integer($this->option('ttl'), Timestamp::units_translation_table("week"));
	}
}
