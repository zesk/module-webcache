<?php
/**
 * @package zesk
 * @subpackage webcache
 * @author kent
 * @copyright &copy; 2017 Market Acumen, Inc.
 */
namespace zesk\WebCache;

/**
 *
 * @author kent
 *
 */
abstract class Iterator implements \Iterator {
	/**
	 * @return Info
	 */
	abstract public function current();
	
	/**
	 * URL stored in cache
	 * 
	 * @return string
	 */
	abstract public function key();
}
