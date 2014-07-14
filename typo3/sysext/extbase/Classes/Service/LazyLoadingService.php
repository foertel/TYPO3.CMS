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
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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
			$uidsToFetch = array();

			foreach ($lazyObjects as $lazyObject) {
				if ($lazyObject instanceof LazyObjectStorage) {
					DebuggerUtility::var_dump($lazyObject);
					die();
				}
				if (is_array($lazyObject->_getFieldValue())) {
					foreach ($lazyObject->_getFieldValue() as $singleFieldValue) {
						$uidsToFetch[$singleFieldValue][] = $lazyObject->_getParentObject();
					}
				} else {
					$uidsToFetch[$lazyObject->_getFieldValue()][] = $lazyObject->_getParentObject();
				}
			}

			$modelClassName = preg_replace('/LazyProxy$/', '', get_class($lazyObject));
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

			$query = $repository->createQuery();
			$query->getQuerySettings()->setRespectStoragePage(FALSE);
			$query->getQuerySettings()->setRespectSysLanguage(FALSE);
			$fetchedObjects = $query->matching($query->in('uid', array_keys($uidsToFetch)))->execute();

			foreach ($fetchedObjects as $fetchedObject) {

				// $object = $repository->findOneByUid($uidToFetch)->getFirst();

				foreach ($uidsToFetch[$fetchedObject->getUid()] as $parentObject) {
					$parentObject->_setProperty($propertyName, $fetchedObject);
					$parentObject->_memorizeCleanState($propertyName);
				}

			}
		}
	}
}
