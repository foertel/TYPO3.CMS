<?php
namespace TYPO3\CMS\Extbase\Persistence\Generic;

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
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * An interface for the lazy loading strategies.
 */
interface LoadingStrategyInterface {

	/**
	 * @param QueryResultInterface $queryResult
	 * @return void
	 */
	public function setParentQueryResult(QueryResultInterface $queryResult);

	/**
	 * Returns the parentObject so we can populate the proxy.
	 *
	 * @return object
	 */
	public function _getParentObject();

	/**
	* Returns the fieldValue so we can fetch multiple LazyObjects in one query.
	*
	* @return mixed
	*/
	public function _getFieldValue();
}
