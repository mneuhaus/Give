<?php

namespace Famelo\Give;

use ErrorException;
use KevinGH\Amend;
use Famelo\Give\Command;
use Famelo\Give\Helper;
use Symfony\Component\Console\Application as Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Sets up the application.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Application extends Base {
	/**
	 * @override
	 */
	public function __construct($name = 'Give', $version = '@git_tag@') {
		// // convert errors to exceptions
		// set_error_handler(
		// 	function ($code, $message, $file, $line) {
		// 		if (error_reporting() & $code) {
		// 			throw new ErrorException($message, 0, $code, $file, $line);
		// 		}
		// 		// @codeCoverageIgnoreStart
		// 	}
		// 	// @codeCoverageIgnoreEnd
		// );

		parent::__construct($name, $version);
	}

	/**
	 * Runs the current application.
	 *
	 * @param InputInterface  $input  An Input instance
	 * @param OutputInterface $output An Output instance
	 *
	 * @return integer 0 if everything went fine, or an error code
	 *
	 * @throws \Exception When doRun returns Exception
	 *
	 * @api
	 */
	public function run(InputInterface $input = NULL, OutputInterface $output = NULL) {
		if (NULL === $input) {
			$command = $_SERVER['argv'][1];
			if (!in_array($command, array('update', 'create', 'list', 'help'))) {
				$argv = $_SERVER['argv'];
				$argv = array_merge(
					array($argv[0]),
					array('create'),
					array_slice($argv, 1)
				);
				$input = new ArgvInput($argv);
			}
		}

		parent::run($input);
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