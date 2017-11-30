<?php
/**
 * @package zesk
 * @subpackage webcache
 * @author kent
 * @copyright &copy; 2017 Market Acumen, Inc.
 */
namespace zesk\WebCache;

/**
 * @author kent
 */
/**
 * 
 */
use zesk\Hookable;
use zesk\Application;

/**
 * 
 * @author kent
 *
 */
abstract class Instance extends Hookable {
	final function __construct(Application $application, array $options = array()) {
		parent::__construct($application, $options);
		$this->initialize();
	}
	
	/**
	 * Set up
	 */
	abstract function initialize();
	/**
	 * Returns an array of [ 'path/to/file', 'filename' ]
	 *
	 * @param unknown $url
	 * @param array $options
	 * @return Info
	 * @throws Exception_NotFound
	 */
	abstract function url($url, array $options = array());
	
	/**
	 * 
	 * @param string $url
	 * @return Info
	 */
	abstract function info($url);
	
	/**
	 * 
	 * @return Iterator
	 */
	abstract function iterator();
}
