<?php
namespace TYPO3\CMS\Extbase\Service;

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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for resolving lazy objects
 */
class LazyLoadingService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper
	 * @inject
	 */
	protected $dataMapper;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * Resolves the lazy objects with their real objects
	 *
	 * @param array $lazyObjects
	 * @param string $propertyName
	 *
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
	 * @return void
	 */
	public function populateLazyObjects(array $lazyObjects = array(), $propertyName) {
		if (!empty($lazyObjects)) {
			$modelClassName = preg_replace('/LazyProxy$/', '', get_class(reset($lazyObjects)));
			$nsSeparator = strpos($modelClassName, '\\') !== FALSE ? '\\\\' : '_';
			$repositoryClassName = preg_replace(
				'/' . $nsSeparator . 'Model' . $nsSeparator . '(?!.*' . $nsSeparator . 'Model' . $nsSeparator . ')/',
				$nsSeparator . 'Repository' . $nsSeparator,
				$modelClassName
			);

			if (class_exists($repositoryClassName)) {
				/** @var \TYPO3\CMS\Extbase\Persistence\RepositoryInterface $repository */
				$repository = $this->objectManager->get($repositoryClassName);
			} else {
				$repository = $this->objectManager->get('TYPO3\CMS\Extbase\Persistence\GenericRepository', $this->objectManager, $modelClassName);
			}

			$uidsToFetch = array();

			foreach ($lazyObjects as $lazyObject) {
				/**
				 * Deal with TYPO3's incredibly stupid comma separated lists "feature"
				 */
				if (strstr($lazyObject->_getFieldValue(), ',')) {
					foreach (GeneralUtility::trimExplode(',', $lazyObject->_getFieldValue()) as $fieldValue) {
						$uidsToFetch[$fieldValue][] = $lazyObject->_getParentObject();
					}
				} else {
					$uidsToFetch[$lazyObject->_getFieldValue()][] = $lazyObject->_getParentObject();
				}
			}

			$fetchedObjects = $repository->findByIdentifier(array_keys($uidsToFetch));

			foreach ($fetchedObjects as $fetchedObject) {
				foreach (array_unique($uidsToFetch[$fetchedObject->getUid()]) as $parentObject) {
					$parentObject->_setProperty($propertyName, $fetchedObject);
					$parentObject->_memorizeCleanState($propertyName);
				}
			}
		}
	}
}
