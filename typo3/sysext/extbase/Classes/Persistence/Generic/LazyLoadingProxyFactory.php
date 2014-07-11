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
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LazyLoadingProxyFactory
 * 
 * @package TYPO3\CMS\Extbase\Persistence\Generic
 * @author  Stefano Kowalke <blueduck@gmx.net>
 */
class LazyLoadingProxyFactory {

	/**
	 * @var \TYPO3\CMS\Core\Cache\CacheManager
	 * @inject
	 */
	protected $cacheManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService
	 * @inject
	 */
	protected $reflectionService;

	/**
	 * @var \TYPO3\CMS\Extbase\Reflection\ClassReflection
	 */
	protected $classReflection;

	/**
	 * The namespace of the proxies
	 *
	 * @var string
	 */
	private $proxyNamespace = '';

	/**
	 * The storage of the proxies
	 * @var \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend
	 */
	private $proxyStorage;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;

	/**
	 * Lifecycle method
	 *
	 * @return void
	 */
	public function initializeObject() {
		$this->proxyStorage = $this->cacheManager->getCache('extbase_lazyproxyobject_storage');
	}

	/**
	 * Returns a lazy proxy from the type of the given object.
	 *
	 * @param string $className The classname or object to create the proxy from
	 * @param string $parentObject The parent object
	 * @param string $propertyName The property
	 * @param string $fieldValue The value of the field
	 *
	 * @return object
	 */
	public function getProxy($className, $parentObject, $propertyName, $fieldValue) {
		$this->classReflection = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Reflection\\ClassReflection', $className);
		$shortName = $this->classReflection->getShortName();
		$this->proxyNamespace = $this->classReflection->getNamespaceName();

		$proxyClassName = $shortName . 'LazyProxy';
		$fqn = $this->proxyNamespace . '\\' . $proxyClassName;

		if (!$this->proxyStorage->has($proxyClassName)) {
			$this->generateProxyClass($className, $proxyClassName);
		}

		$this->proxyStorage->requireOnce($proxyClassName);

		return new $fqn($parentObject, $propertyName, $fieldValue);

	}

	/**
	 * Generates the php proxy file.
	 * Will be saved into cache
	 *
	 * @param $className
	 * @param $proxyClassName
	 */
	protected function generateProxyClass($className, $proxyClassName) {
		$placeholders = array(
			'<namespace>',
			'<proxyClassName>',
			'<className>',
			'<methods>',
		);

		$methods = $this->generateMethods();
		
		$proxyTemplate = $this->getProxyTemplate();

		$replacements = array(
			$this->proxyNamespace,
			$proxyClassName,
			$className,
			$methods
		);

		$file = str_replace($placeholders, $replacements, $proxyTemplate);
		$this->proxyStorage->set($proxyClassName, $file);
	}

	/**
	 * Returns the proxy file template
	 *
	 * @return string
	 */
	private function getProxyTemplate() {
		return GeneralUtility::getUrl(ExtensionManagementUtility::extPath('extbase') . '/Resources/Private/LazyProxyTemplate.txt');
	}

	/**
	 * Generates the methods for the proxy object
	 *
	 * @return string $methods
	 */
	private function generateMethods() {
		$methods = '';

		foreach ($this->classReflection->getMethods() as $method) {
			if ($method->isConstructor()) {
				continue;
			}

			$methodName = $method->getName();

			if ($method->isPublic() && !$method->isFinal() && !$method->isStatic()) {
				if (strstr('set', strtolower($methodName))) {
					continue;
				}

				$methods .= PHP_EOL . ' public function ';
				if ($method->returnsReference()) {
					$methods .= '&';
				}

				$methods .= $methodName . '(';
				$firstParameter = TRUE;
				$parameterString = $argumentString = '';

				foreach ($method->getParameters() as $parameter) {
					if ($firstParameter) {
						$firstParameter = FALSE;
					} else {
						$parameterString .= ',';
						$argumentString .= ',';
					}

					if (($paramClass = $parameter->getClass()) !== NULL) {
						$parameterString .= '\\' . $paramClass->getName() . ' ';
					} else if ($parameter->isArray()) {
						$parameterString .= 'array ';
					}

					if ($parameter->isPassedByReference()) {
						$parameterString .= '&';
					}

					$parameterString .= '$' . $parameter->getName();
					$argumentString .= '$' . $parameter->getName();

					if ($parameter->isDefaultValueAvailable()) {
						$parameterString .= ' = ' . var_export($parameter->getDefaultValue(), TRUE);
					}
				}

				$methods .= $parameterString . ') {' . PHP_EOL;
				if (!(strcasecmp($methodName, '_setClone') === 0
					|| strcasecmp($methodName, '__clone') === 0)) {
					$methods .= '    $this->parentQueryResult->fetchLazyObjects($this->propertyName);' . PHP_EOL;
				}
				$methods .= '    return parent::' . $method->getName() . '(' . $argumentString . ');';
				$methods .= PHP_EOL . '}' . PHP_EOL;
			}
		}

		return $methods;
	}
}