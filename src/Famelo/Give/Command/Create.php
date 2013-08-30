<?php

namespace Famelo\Give\Command;

use Famelo\Give\Configuration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Traversable;

/**
 * Builds a new Phar.
 *
 */
class Create extends Command {
	/**
	 * The Box instance.
	 *
	 * @var Box
	 */
	private $box;

	/**
	 * The configuration settings.
	 *
	 * @var Configuration
	 */
	private $config;

	/**
	 * The output handler.
	 *
	 * @var OutputInterface
	 */
	private $output;

	/**
	 * @var array
	 */
	private $variables = array();

	/**
	 * @override
	 */
	protected function configure() {
		parent::configure();
		$this->setName('create');
		$this->setDescription('Clone and prepare a new Project');
		$this->addArgument(
				'repository',
				InputArgument::OPTIONAL,
				'The GitHub Repository to clone from'
		);
		$this->addArgument(
				'name',
				InputArgument::OPTIONAL,
				'The name'
		);
	}

	/**
	 * @override
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$knownRepositoryStorage = exec('cd ~ && pwd') . '/.give-repositories';
		if (file_exists($knownRepositoryStorage)) {
			$knownRepositories = json_decode(file_get_contents($knownRepositoryStorage));
		} else {
			$knownRepositories = array('[Enter a new one]');
		}

		if ($input->getArgument('repository') === NULL) {
			$repository = $this->getHelperSet()->get('dialog')->select(
				$output,
				'Please choose a Repository',
				$knownRepositories
			);
			if ($repository == 0) {
				$repository = $this->getHelperSet()->get('dialog')->ask(
					$output,
					'Please enter a repository: '
				);
			} else {
				$repository = $knownRepositories[$repository];
			}
			$input->setArgument('repository', $repository);
			$name = $this->getHelperSet()->get('dialog')->ask(
				$output,
				'Please enter a Name: '
			);
			$input->setArgument('name', $name);
		}

		$this->output = $output;
		$this->input = $input;

		$targetPath = getcwd() . '/' . $input->getArgument('name');
		$this->cloneRepositories($input->getArgument('repository'), $targetPath);
		$this->fetchVariables($targetPath);
		$this->process($targetPath);

		if ($input->getArgument('repository') !== NULL) {
			$knownRepositories[] = $input->getArgument('repository');
		}
		$knownRepositories = array_unique($knownRepositories);
		$knownRepositories = array_values($knownRepositories);
		$jsonPretty = new \Camspiers\JsonPretty\JsonPretty;
		file_put_contents($knownRepositoryStorage, $jsonPretty->prettify($knownRepositories));
	}

	public function cloneRepositories($repository, $targetPath) {
		$this->cloneRepository($repository, $targetPath);

		$configFile = $targetPath . '/give.json';

		if (!file_exists($configFile)) {
			$this->output->write('<comment>No give.json found!</comment>' . chr(10));
			return;
		}

		$config = $this->getConfig($configFile);

		$previousPath = getcwd();
		chdir($targetPath);

		$defaults = $config->getDefaults();

		foreach ($config->getSubmodules() as $submodule) {
			$this->output->write('<info>Fetching submodule: </info>' . $submodule->module . chr(10));
			$path = $this->renderString($submodule->path, $defaults);
			$this->cloneRepositories($submodule->module, $path);
		}

		chdir($previousPath);
	}

	public function fetchVariables($targetPath, $overrideVariables = array()) {
		$configFile = $targetPath . '/give.json';

		if (!file_exists($configFile)) {
			$this->output->write('<comment>No give.json found!</comment>' . chr(10));
			return;
		}

		$config = $this->getConfig($configFile);

		$variables = array(
			'name' => $this->input->getArgument('name')
		);

		// $this->output->write('<info>Checking Variables</info>' . chr(10));
		$dialog = $this->getHelperSet()->get('dialog');
		foreach ($config->getVariables() as $variable) {
			$default = isset($variable->default) ? $variable->default : NULL;
			$question = $variable->question . ' [' . $default . ']: ';
			$variables[$variable->name] = $dialog->ask(
					$this->output,
					$question,
					$default
			);
		}

		$variables = array_merge($variables, $overrideVariables);
		$this->variables[$targetPath] = $variables;

		$previousPath = getcwd();
		chdir($targetPath);

		$defaults = $config->getDefaults();

		foreach ($config->getSubmodules() as $submodule) {
			$subVariables = array();
			if (isset($submodule->variables)) {
				foreach ($submodule->variables as $key => $value) {
					$subVariables[$key] = $this->renderString($value, $variables);
				}
			}
			$path = $this->renderString($submodule->path, $defaults);
			$this->fetchVariables($path, $subVariables);
		}

		chdir($previousPath);
	}

	public function process($targetPath, $overrideVariables = array()) {
		$configFile = $targetPath . '/give.json';

		if (!file_exists($configFile)) {
			$this->output->write('<comment>No give.json found!</comment>' . chr(10));
			return;
		}

		$config = $this->getConfig($configFile);

		$variables = $this->variables[$targetPath];

		$this->output->write('<info>Processing Replacements...</info>' . chr(10));
		foreach ($config->getReplacements() as $replacement) {
			$this->replace(
				$targetPath,
				$replacement,
				$variables);
		}

		$this->output->write('<info>Renaming Files...</info>' . chr(10));
		foreach ($config->getRename() as $rename) {
			$this->rename(
				$targetPath,
				$rename,
				$variables);
		}

		$previousPath = getcwd();
		chdir($targetPath);

		foreach ($config->getPostProcessCommands() as $command) {
			$this->output->write('<info>Executing command: </info>' . $command->command . chr(10));
			$processBuilder = new ProcessBuilder();
			$processBuilder->inheritEnvironmentVariables(TRUE);

			foreach (explode(' ', $command->command) as $part) {
				$processBuilder->add($part);
			}

			$output = $this->output;
			$processBuilder->getProcess()->run(function($type, $data) use ($output) {
				$output->write($data);
			});
		}

		$defaults = $config->getDefaults();

		foreach ($config->getSubmodules() as $submodule) {
			$this->output->write('<info>Processing submodule: </info>' . $submodule->module . chr(10));
			$defaultPath = $this->renderString($submodule->path, $defaults);
			$path = $this->renderString($submodule->path, $variables);
			if ($path !== $defaultPath) {
				$this->variables[$path] = $this->variables[$defaultPath];
				rename($defaultPath, $path);
			}
			$subVariables = array();
			if (isset($submodule->variables)) {
				foreach ($submodule->variables as $key => $value) {
					$subVariables[$key] = $this->renderString($value, $variables);
				}
			}
			$this->process($path, $subVariables);
		}

		chdir($previousPath);

		if ($config->getComment() !== NULL) {
			$this->output->write($this->renderString($config->getComment(), $variables) . chr(10));
		}
	}

	public function getConfig($filename) {
		$helper = $this->getHelper('config');
		return $helper->loadFile($filename);
	}

	public function replace($dir, $config, $context) {
		$finder = new Finder();
		$iterator = $finder
			->files()
			->in($dir);

		foreach (explode(',', 'name,contains,notContains,path,notPath,depth') as $filter) {
			if (isset($config->$filter)) {
				$iterator->$filter($config->$filter);
			}
		}

		$replace = $this->renderString($config->replace, $context);
		foreach ($iterator as $file) {
			$content = file_get_contents($file->getRealpath());
			$content = str_replace($config->search, $replace, $content);
			file_put_contents($file->getRealpath(), $content);
		}
	}

	public function rename($dir, $config, $context) {
		$finder = new Finder();
		$iterator = $finder
			->files()
			->in($dir);

		foreach (explode(',', 'name,contains,notContains,path,notPath,depth') as $filter) {
			if (isset($config->$filter)) {
				$iterator->$filter($config->$filter);
			}
		}

		$target = $this->renderString($config->target, $context);
		$this->output->write('Renaming ' . $config->source . ' to ' . $target . chr(10));

		$fs = new Filesystem();
		$fs->mkdir(dirname($dir . '/' . $target));
		rename($dir . '/' . $config->source, $dir . '/' . $target);

		$source = $config->source;
		while (($source = dirname($source)) !== '.') {
			$finder = new Finder();
			$iterator = $finder
				->files()
				->path($source)
				->in($dir);
			if ($iterator->count() === 0) {
				$this->output->write('Removing empty dir: ' . $source . chr(10));
				$fs->remove($dir . '/' . $source);
			}
		}
	}

	public function renderString($string, $context) {
		$loader = new \Twig_Loader_String();
		$twig = new \Twig_Environment($loader);
		return $twig->render($string, $context);
	}

	public function cloneRepository($repository, $path) {
		$pb = new ProcessBuilder();

		if (stristr($repository, ':')) {
			$parts = explode(':', $repository);
			$repository = $parts[0];
			$branch = $parts[1];
			$process = $pb
				->add('git')
				->add('clone')
				->add('git@github.com:' . $repository . '.git')
				->add('-b')
				->add($branch)
				->add($path)
				->inheritEnvironmentVariables(TRUE)
				->getProcess();
		} else {
			$process = $pb
				->add('git')
				->add('clone')
				->add('git@github.com:' . $repository . '.git')
				->add($path)
				->inheritEnvironmentVariables(TRUE)
				->getProcess();
		}

		$output = $this->output;
		$process->run(function($type, $data) use ($output) {
			if (stristr($data, 'fatal:')) {
				$output->write('<error>The repository doesn\'t seem to exist. Spelling?</error>' . chr(10));
				$output->write($data . chr(10));
			} else {
				$output->write($data);
			}
		});
	}

	public function addSubmodule($repository, $path) {
		$this->addToGitIgnore($path);
		$this->cloneRepository($repository, $path);
	}

	public function addToGitIgnore($line) {
		$lines = array();

		if (file_exists('.gitignore')) {
			$lines = explode(chr(10), file_get_contents('.gitignore'));
		}

		$lines[] = $line;
		$lines = array_unique($lines);

		file_put_contents('.gitignore', implode(chr(10), $lines));
	}

	public function executeShellCommand($command) {
		$output = '';
		$fp = popen($command, 'r');
		while (($line = fgets($fp)) !== FALSE) {
			$output .= $line;
		}
		pclose($fp);
		return trim($output);
	}
}

?>