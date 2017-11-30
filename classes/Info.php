<?php
/**
 * @package zesk
 * @subpackage webcache
 * @author kent
 * @copyright &copy; 2017 Market Acumen, Inc.
 */
namespace zesk\WebCache;

/**
 * Information for each WebCache entry
 * 
 * Trying to only store scalar values in this object for now.
 * 
 * @author kent
 */
abstract class Info {
	/**
	 * Current version of this structure. Bump ONLY for incompatible changes.
	 * 
	 * @var integer
	 */
	const VERSION = 1;
	
	/**
	 * 
	 * @var integer
	 */
	public $ttl = null;
	
	/**
	 * 
	 * @var string
	 */
	public $hash = null;
	
	/**
	 * 
	 * @var integer
	 */
	public $size = null;
	
	/**
	 * 
	 * @var boolean
	 */
	public $status = null;
	
	/**
	 * Unix timestamp
	 * 
	 * @var integer
	 */
	public $created = null;
	
	/**
	 * 
	 * @var string
	 */
	public $response_code = null;
	
	/**
	 * 
	 * @var string
	 */
	public $name = null;
	
	/**
	 * 
	 * @var string
	 */
	public $url = null;
	
	/**
	 * Structure version, currently 1.
	 * 
	 * @var string
	 */
	public $version = null;
	
	/**
	 * 
	 * @param array $array
	 * @return \zesk\WebCache\Info
	 */
	public static function factory($class, array $array) {
		$object = new $class();
		return $object->from_array($array);
	}
	/**
	 * 
	 * @param array $array
	 * @return self
	 */
	public function from_array(array $array) {
		foreach (get_object_vars($this) as $key => $value) {
			$this->$key = $value;
		}
		return $this;
	}
	
	/**
	 * 
	 * @return string[]
	 */
	public function to_array() {
		return array(
			"version" => self::VERSION
		) + get_object_vars($this);
	}
	
	/**
	 * Delete this Info (related to parent $instance)
	 * 
	 * @param Instance $instance
	 */
	abstract function delete(Instance $instance);
}

