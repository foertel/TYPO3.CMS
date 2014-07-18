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
use TYPO3\CMS\Extbase\Persistence\Generic\LazyObjectStorage;

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
	 * @param array $lazyObjects
	 * @param $propertyName
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
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
				$repository = $this->objectManager->get($repositoryClassName);
			} else {
				$repository = $this->objectManager->get('TYPO3\CMS\Extbase\Persistence\GenericRepository', $this->objectManager, $modelClassName);
			}

			$uidsToFetch = array();

			foreach ($lazyObjects as $lazyObject) {
				// @todo check for CSV *uarg* and trimExplode
				$uidsToFetch[$lazyObject->_getFieldValue()][] = $lazyObject->_getParentObject();
			}



			$query = $repository->createQuery();
			$query->getQuerySettings()->setRespectStoragePage(FALSE);
			$query->getQuerySettings()->setRespectSysLanguage(FALSE);
			$fetchedObjects = $query->matching($query->in('uid', array_keys($uidsToFetch)))->execute();

			foreach ($fetchedObjects as $fetchedObject) {
				foreach ($uidsToFetch[$fetchedObject->getUid()] as $parentObject) {
					$parentObject->_setProperty($propertyName, $fetchedObject);
					$parentObject->_memorizeCleanState($propertyName);
				}

			}
		}
	}
}
