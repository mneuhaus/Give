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
		 * @override
		 */
		protected function configure() {
				parent::configure();
				$this->setName('create');
				$this->setDescription('Clone and prepare a new Project');
				$this->addArgument(
						'repository',
						InputArgument::REQUIRED,
						'The GitHub Repository to clone from'
				);
				$this->addArgument(
						'name',
						InputArgument::REQUIRED,
						'The name'
				);
		}

		/**
		 * @override
		 */
		protected function execute(InputInterface $input, OutputInterface $output) {
				$this->output = $output;

				$pb = new ProcessBuilder();

				$process = $pb
					->add('git')
					->add('clone')
					->add('git@github.com:' . $input->getArgument('repository') . '.git')
					->add($input->getArgument('name'))
					->inheritEnvironmentVariables(TRUE)
					->getProcess();

				$output = $this->output;
				$process->run(function($type, $data) use ($output) {
					$output->writeln($data);
				});

				$targetPath = getcwd() . '/' . $input->getArgument('name');

				if (!file_exists($targetPath . '/give.json')) {
					$this->output->write('<comment>No give.json found!</comment>' . chr(10));
					return;
				}

				$config = $this->getConfig($targetPath . '/give.json');

				$variables = array(
					'name' => $input->getArgument('name')
				);

				$this->output->write('<info>Checking Variables</info>' . chr(10));
				$dialog = $this->getHelperSet()->get('dialog');
				foreach ($config->getVariables() as $variable) {
					$default = isset($variable->default) ? $variable->default : NULL;
					$question = $variable->question . ' [' . $default . ']: ';
					$variables[$variable->name] = $dialog->ask(
							$output,
							$question,
							$default
					);
				}

				$this->output->write('<info>Processing Replacements...</info>' . chr(10));
				foreach ($config->getReplacements() as $replacement) {
					$this->replace(
						getcwd() . '/' . $input->getArgument('name'),
						$replacement,
						$variables);
				}

				$this->output->write('<info>Renaming Files...</info>' . chr(10));
				foreach ($config->getRename() as $rename) {
					$this->rename(
						getcwd() . '/' . $input->getArgument('name'),
						$rename,
						$variables);
				}

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
						$output->writeln($data);
					});
				}

				$this->output->write('Done!' . chr(10));
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

			$loader = new \Twig_Loader_String();
			$twig = new \Twig_Environment($loader);
			$replace = $twig->render($config->replace, $context);
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

			$loader = new \Twig_Loader_String();
			$twig = new \Twig_Environment($loader);
			$target = $twig->render($config->target, $context);
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

}
