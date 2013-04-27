<?php

namespace Famelo\Give;

use ErrorException;
use KevinGH\Amend;
use Famelo\Give\Command;
use Famelo\Give\Helper;
use Symfony\Component\Console\Application as Base;

/**
 * Sets up the application.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Application extends Base {
	/**
	 * @override
	 */
	public function __construct($name = 'Box', $version = '@git_tag@') {
		// convert errors to exceptions
		set_error_handler(
			function ($code, $message, $file, $line) {
				if (error_reporting() & $code) {
					throw new ErrorException($message, 0, $code, $file, $line);
				}
				// @codeCoverageIgnoreStart
			}
			// @codeCoverageIgnoreEnd
		);

		parent::__construct($name, $version);
	}

	/**
	 * @override
	 */
	protected function getDefaultCommands() {
		$commands = parent::getDefaultCommands();
		$commands[] = new Command\Create();

		if (('@' . 'git_tag@') !== $this->getVersion()) {
			$command = new Amend\Command('update');
			$command->setManifestUri('@manifest_url@');

			$commands[] = $command;
		}

		return $commands;
	}

	/**
	 * @override
	 */
	protected function getDefaultHelperSet() {
		$helperSet = parent::getDefaultHelperSet();
		$helperSet->set(new Helper\ConfigurationHelper());

		if (('@' . 'git_tag@') !== $this->getVersion()) {
			$helperSet->set(new Amend\Helper());
		}

		return $helperSet;
	}
}

?>