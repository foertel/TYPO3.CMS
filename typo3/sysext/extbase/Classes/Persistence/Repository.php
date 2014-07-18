<?php
namespace TYPO3\CMS\Extbase\Persistence;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * The base repository - will usually be extended by a more concrete repository.
 *
 * @api
 */
class Repository extends GenericRepository implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * Figures out the type of domain models, based on the conrete implementaion's classname.
	 *
	 * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
	 */
	public function __construct(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager) {
		parent::__construct($objectManager);

		$nsSeparator = strpos($this->getRepositoryClassName(), '\\') !== FALSE ? '\\\\' : '_';
		$this->objectType = preg_replace(
			array(
				'/' . $nsSeparator . 'Repository' . $nsSeparator . '(?!.*' . $nsSeparator . 'Repository' . $nsSeparator . ')/',
				'/Repository$/'
			),
			array($nsSeparator . 'Model' . $nsSeparator, ''),
			$this->getRepositoryClassName()
		);
	}
}
