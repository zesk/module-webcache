<?php
/**
 * @package zesk
 * @subpackage webcache
 * @author kent
 * @copyright &copy; 2017 Market Acumen, Inc.
 */
namespace zesk\WebCache;

use zesk\Exception;
use zesk\arr;

/**
 * The WebCache module handles loading WebCache configuration, managing instances, and running cron periodically to update the WebCache
 * 
 * @author kent
 *
 */
class Module extends \zesk\Module {
	/**
	 * 
	 * @var Instance[]
	 */
	private $instances = null;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \zesk\Module::initialize()
	 */
	public function initialize() {
		$this->options['name'] = "Web Cache";
		$this->codename = __CLASS__;
		$this->path = dirname(__DIR__);
// 		$this->application->register_class(array(
// 			Info::class,
// 			Instance::class,
// 			Iterator::class,
// 			Local\Info::class,
// 			Local\Instance::class,
// 			Local\Iterator::class
// 		));
	}
	/**
	 * 
	 * @param array $options
	 * @return Instance
	 */
	public function factory(array $options = array()) {
		$class = avalue($options, "class", Local\Instance::class);
		return $this->application->factory($class, $this->application, $options);
	}
	
	/**
	 * 
	 */
	public function hook_configured() {
		$result = $this->application->configuration->deprecated("File_Cache::ttl", __CLASS__ . "::defaults::ttl");
		$result = $this->application->configuration->deprecated("File_Cache::path", __CLASS__ . "::instances::default::path") || $result;
		if ($result) {
			// Pick up new settings
			$this->inherit_global_options();
		}
	}
	
	/**
	 * Retrieve one WebCache instance
	 * 
	 * @param string $name
	 * @return Instance
	 */
	public function instance($name = null) {
		return $name === null ? first($this->instances()) : avalue($this->instances(), $name, null);
	}
	/**
	 * Return configured WebCache instances 
	 * 
	 * @return Instance[]
	 */
	public function instances() {
		if (is_array($this->instances)) {
			return $this->instances;
		}
		$defaults = $this->option_array("defaults");
		$instances = $this->option_array("instances");
		foreach ($instances as $code => $settings) {
			if (!is_array($settings)) {
				$this->application->logger->error("{class}::instances::{code} should be an associative array, is type {type} ... skipping", array(
					"class" => __CLASS__,
					"code" => $code,
					"type" => type($settings)
				));
				continue;
			}
			try {
				$instance = $this->factory(array(
					"code" => $code
				) + $settings + $defaults);
			} catch (\Exception $e) {
				$this->application->logger->error("{class}::instances::{code} Failed to create with exception {exception.class} {exception.message}", array(
					"class" => __CLASS__,
					"code" => $code
				) + arr::kprefix(Exception::exception_variables($e), "exception."));
			}
			$instances[$code] = $instance;
		}
		return $this->instances = $instances;
	}
	
	/**
	 * Run hourly
	 */
	public function hook_cron_hour() {
		$instances = $this->instances();
		foreach ($instances as $code => $instance) {
			// Expire cache
			foreach ($instance->iterator() as $url => $info) {
				$this->application->logger->debug("{url} found in cache TODO", array(
					"url" => $url
				));
				$info->expire();
			}
		}
	}
	
	/**
	 * 
	 * @return string[][]
	 */
	function settings() {
		return array(
			__CLASS__ . "::instances" => array(
				"label" => __("Instances"),
				"description" => __("Array of initialization options for a WebCache instance"),
				"type" => "array"
			),
			__CLASS__ . "::defaults" => array(
				"label" => __("Defaults"),
				"description" => __("Array of default initialization options for all WebCache instances"),
				"type" => "array"
			),
			__CLASS__ . "::instances::{name}::path" => array(
				"label" => __("Path for a zesk\WebCache\Local"),
				"description" => "Number of seconds",
				"type" => "directory"
			),
			__CLASS__ . "::instances::{name}::ttl" => array(
				"label" => __("Time to live"),
				"description" => "Number of seconds",
				"units" => "seconds",
				"type" => "integer"
			)
		);
	}
}
