<?php

namespace Famelo\Give;

use ArrayIterator;
use Herrera\Box\Compactor\CompactorInterface;
use InvalidArgumentException;
use Phar;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Manages the configuration settings.
 *
 */
class Configuration
{
	/**
	 * The configuration file path.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * The raw configuration settings.
	 *
	 * @var object
	 */
	private $raw;

	/**
	 * Sets the raw configuration settings.
	 *
	 * @param string $file The configuration file path.
	 * @param object $raw  The raw settings.
	 */
	public function __construct($file, $raw) {
		$this->file = $file;
		$this->raw = $raw;
	}

	public function getReplacements() {
		if (isset($this->raw->replacements)) {
			return $this->raw->replacements;
		}

		return array();
	}

	public function getRename() {
		if (isset($this->raw->rename)) {
			return $this->raw->rename;
		}

		return array();
	}

	public function getVariables() {
		if (isset($this->raw->variables)) {
			return $this->raw->variables;
		}

		return array();
	}

	public function getPostProcessCommands() {
		if (isset($this->raw->postProcessCommands)) {
			return $this->raw->postProcessCommands;
		}

		return array();
	}

	public function getSubmodules() {
		if (isset($this->raw->submodules)) {
			return $this->raw->submodules;
		}

		return array();
	}

	public function getComment() {
		if (isset($this->raw->comment)) {
			return $this->raw->comment;
		}

		return array();
	}

	public function getDefaults() {
		$defaults = array();
		foreach ($this->getVariables() as $variable) {
			$defaults[$variable->name] = $variable->default;
		}
		return $defaults;
	}

}

?>