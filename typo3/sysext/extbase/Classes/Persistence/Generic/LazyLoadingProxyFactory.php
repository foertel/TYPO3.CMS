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
use ReflectionClass;
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
	 * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService
	 * @inject
	 */
	protected $reflectionService;

	/**
	 * The namespace of the proxies
	 *
	 * @var string
	 */
	private $proxyNamespace = '';

	/**
	 * The storage of the proxies
	 * @var mixed
	 */
	private $proxyStorage;

	/**
	 * @var \ReflectionClass $classReflection
	 */
	private $classReflection;

	/**
	 * Lifecycle method
	 *
	 * @return void
	 */
	public function initializeObject() {
		$this->proxyStorage = 'typo3temp';
	}

	/**
	 * @param $className
	 * @param $parentObject
	 * @param $propertyName
	 * @param $fieldValue
	 *
	 * @return
	 * @internal param $identifier
	 */
	public function getProxy($className, $parentObject, $propertyName, $fieldValue) {
		$this->classReflection = $this->reflectionService->getClassSchema($className);
		$shortName = $this->classReflection->getShortName();
		$this->proxyNamespace = $this->classReflection->getNamespaceName();

		$proxyClassName = $shortName . 'LazyProxy';
		$fqn = $this->proxyNamespace . '\\' . $proxyClassName;

		$proxyFileName = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'typo3temp' . DIRECTORY_SEPARATOR . $proxyClassName . '.php';
		$this->generateProxyClass($className, $proxyClassName, $proxyFileName);

		require_once $proxyFileName;

		return new $fqn($parentObject, $propertyName, $fieldValue);

	}

	/**
	 * @param $className
	 * @param $proxyClassName
	 * @param $fileName
	 */
	protected function generateProxyClass($className, $proxyClassName, $fileName) {
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

		file_put_contents($fileName, $file);
	}

	private function getProxyTemplate() {
		return GeneralUtility::getUrl(ExtensionManagementUtility::extPath('extbase') . '/Resources/Private/LazyProxyTemplate.txt');
	}

	/**
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
				if (substr_compare($methodName, '__', 0, 1) !== 0) {
					$methods .= '    $this->parentQueryResult->fetchLazyObjects($this->propertyName);' . PHP_EOL;
				}
				$methods .= '    return parent::' . $method->getName() . '(' . $argumentString . ');';
				$methods .= PHP_EOL . '}' . PHP_EOL;
			}
		}

		return $methods;
	}
}