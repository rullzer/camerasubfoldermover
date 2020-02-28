<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\CameraSubFolderMover\Command;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Mover extends Command {

	/** @var IUserManager */
	private $userManager;

	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(IUserManager $userManager,
								IRootFolder $rootFolder) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;

	}

	public function configure() {
		$this->setName('files:photo_move')
			->setDescription('Move instant upload pictures out of subfolders')
			->addOption(
				'user',
				'u',
				InputOption::VALUE_REQUIRED,
				'The user to move the files for'
			)
			->addOption(
				'path',
				'p',
				InputOption::VALUE_REQUIRED,
				'The folder of the Instant Upload folder'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$user = $input->getOption('user');

		if ($this->userManager->get($input->getOption('user')) === null) {
			$output->writeln('Invalid user');
			return;
		}

		$path = $input->getOption('path');

		$userFolder = $this->rootFolder->getUserFolder($user);
		$pictureFolder = $userFolder->get($path);

		$folders = $this->getFolders($pictureFolder);
		foreach ($folders as $folder) {
			$this->move($folder);
		}
	}

	protected function getFolders(Folder $folder): iterable {
		$nodes = $folder->getDirectoryListing();

		foreach ($nodes as $node) {
			if ($node instanceof File) {
				continue;
			}

			if (preg_match('/^IMG_.*$/', $node->getName()) === 1) {
				yield $node;
			} else {
				yield from $this->getFolders($node);
			}
		}

		return [];
	}

	protected function move(Folder $folder) {
		$parent = $folder->getParent();

		$canDelete = true;

		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			$target = $parent->getPath() . '/' . $node->getName();

			if (!$parent->nodeExists($node->getName())) {
				$node->move($target);
			} else {
				$canDelete = false;
			}
		}

		if ($canDelete) {
			$folder->delete();
		}
	}


}
