<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\ObjectManager;

use Magento\Framework\Code\Reader\ClassReaderInterface;
use Magento\Framework\ObjectManagerInterface;

/**
 * Class TMap
 * @internal
 */
class TMap implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $array = [];

    /**
     * @var array
     */
    private $objectsArray = [];

    /**
     * @var int
     */
    private $counter = 0;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ConfigInterface
     */
    private $configInterface;

    /**
     * @var ClassReaderInterface
     */
    private $classReaderInterface;


    /**
     * @param string $type
     * @param ObjectManagerInterface $objectManager
     * @param ConfigInterface $configInterface
     * @param ClassReaderInterface $classReaderInterface
     * @param array $array
     */
    public function __construct(
        $type,
        ObjectManagerInterface $objectManager,
        ConfigInterface $configInterface,
        ClassReaderInterface $classReaderInterface,
        array $array = []
    ) {
        if (!class_exists($this->type) && !interface_exists($type)) {
            throw new \InvalidArgumentException(sprintf('Unknown type %s', $type));
        }

        $this->type = $type;

        $this->objectManager = $objectManager;
        $this->configInterface = $configInterface;
        $this->classReaderInterface = $classReaderInterface;

        array_walk(
            $array,
            function ($item, $index) {
                $this->assertValidTypeLazy($item, $index);
            }
        );

        $this->array = $array;
        $this->counter = count($array);
    }

    /**
     * Asserts that item type is collection defined type
     *
     * @param string $instanceName
     * @param string|int|null $index
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertValidTypeLazy($instanceName, $index = null)
    {
        $realType = $this->configInterface->getInstanceType(
            $this->configInterface->getPreference($instanceName)
        );

        if (!in_array($this->type, $this->classReaderInterface->getParents($realType), true)) {
            $this->throwTypeException($realType, $index);
        }
    }

    /**
     * Throws exception according type mismatch
     *
     * @param string $inputType
     * @param string $index
     * @return void
     * @throws \InvalidArgumentException
     */
    private function throwTypeException($inputType, $index)
    {
        $message = 'Type mismatch. Expected type: %s. Actual: %s, Code: %s';

        throw new \InvalidArgumentException(
            sprintf($message, $this->type, $inputType, $index)
        );
    }

    /**
     * Returns object for requested index
     *
     * @param string|int $index
     * @return object
     */
    private function initObject($index)
    {
        if (isset($this->objectsArray[$index])) {
            return $this->objectsArray[$index];
        }

        return $this->objectsArray[$index] = $this->objectManager->create($this->array[$index]);
    }

    /**
     * {inheritdoc}
     */
    public function getIterator()
    {
        if (array_keys($this->array) != array_keys($this->objectsArray)) {
            foreach (array_keys($this->array) as $index) {
                $this->initObject($index);
            }
        }

        return new \ArrayIterator($this->objectsArray);
    }

    /**
     * {inheritdoc}
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->array);
    }

    /**
     * {inheritdoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->array[$offset]) ? $this->initObject($offset) : null;
    }

    /**
     * {inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->assertValidTypeLazy($value, $offset);
        if ($offset === null) {
            $this->array[] = $value;
        } else {
            $this->array[$offset] = $value;
        }

        $this->counter++;
    }

    /**
     * {inheritdoc}
     */
    public function offsetUnset($offset)
    {
        if ($this->counter && isset($this->array[$offset])) {
            $this->counter--;
            unset($this->array[$offset]);

            if (isset($this->objectsArray[$offset])) {
                unset($this->objectsArray[$offset]);
            }
        }
    }

    /**
     * {inheritdoc}
     */
    public function count()
    {
        return $this->counter;
    }
}
