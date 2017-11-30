<?php
/**
 * @package zesk
 * @subpackage webcache
 * @author kent
 * @copyright &copy; 2017 Market Acumen, Inc.
 */
namespace zesk\WebCache\Local;

use zesk\File;

/**
 *
 * @author kent
 *
 */
class Info extends \zesk\WebCache\Info {
	/**
	 * 
	 * {@inheritDoc}
	 * @see \zesk\WebCache\Info::delete()
	 */
	function delete(Instance $instance) {
		$hash = $this->hash;
		if ($hash) {
			$files[] = $instance->settings_path($hash, false);
			$files[] = $instance->binary_file_path($hash, false);
			foreach ($files as $file) {
				File::unlink($file);
			}
		}
	}
}