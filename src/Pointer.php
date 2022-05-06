<?php

namespace SoftInvest\Json;

use SoftInvest\Json\Pointer\InvalidJsonException;
use SoftInvest\Json\Pointer\InvalidPointerException;
use SoftInvest\Json\Pointer\NonexistentValueReferencedException;
use SoftInvest\Json\Pointer\NonWalkableJsonException;
use stdClass;

class Pointer
{
    const POINTER_CHAR = '/';
    const LAST_ARRAY_ELEMENT_CHAR = '-';

    /**
     * @var array
     */
    private $json;

    /**
     * @var string
     */
    private $pointer;

    /**
     * @param string $json The Json structure to point through.
     * @throws \SoftInvest\Json\Pointer\InvalidJsonException
     * @throws \SoftInvest\Json\Pointer\NonWalkableJsonException
     */
    public function __construct($json)
    {
        $this->json = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidJsonException('Cannot operate on invalid Json.');
        }

        if (!$this->isWalkableJson()) {
            throw new NonWalkableJsonException('Non walkable Json to point through');
        }
    }

    /**
     * @return boolean
     */
    private function isWalkableJson(): bool
    {
        if ($this->json !== null && (is_array($this->json) || $this->json instanceof stdClass)) {
            return true;
        }
        return false;
    }

    /**
     * @param string $pointer The Json Pointer.
     * @return mixed
     * @throws \SoftInvest\Json\Pointer\NonexistentValueReferencedException
     *
     * @throws \SoftInvest\Json\Pointer\InvalidPointerException
     */
    public function get($pointer)
    {
        if ($pointer === '') {
            $output = json_encode($this->json, JSON_UNESCAPED_UNICODE);
            // workaround for https://bugs.php.net/bug.php?id=46600
            return str_replace('"_empty_"', '""', $output);
        }

        $this->validatePointer($pointer);

        $this->pointer = $pointer;

        $plainPointerParts = array_slice(
            array_map('urldecode', explode('/', $pointer)),
            1
        );
        return $this->traverse($this->json, $this->evaluatePointerParts($plainPointerParts));
    }

    /**
     * @param string $pointer The Json Pointer to validate.
     * @throws \SoftInvest\Json\Pointer\InvalidPointerException
     */
    private function validatePointer($pointer)
    {
        if ($pointer !== '' && !is_string($pointer)) {
            throw new InvalidPointerException('Pointer is not a string');
        }

        $firstPointerCharacter = substr($pointer, 0, 1);

        if ($firstPointerCharacter !== self::POINTER_CHAR) {
            throw new InvalidPointerException('Pointer starts with invalid character');
        }
    }

    /**
     * @param array|\stdClass $json The json_decoded Json structure.
     * @param array $pointerParts The parts of the fed pointer.
     *
     * @return mixed
     * @throws \SoftInvest\Json\Pointer\NonexistentValueReferencedException
     *
     */
    private function traverse(&$json, array $pointerParts)
    {
        $pointerPart = array_shift($pointerParts);

        if (is_array($json) && isset($json[$pointerPart])) {
            if (count($pointerParts) === 0) {
                return $json[$pointerPart];
            }
            if ((is_array($json[$pointerPart]) || is_object($json[$pointerPart])) && is_array($pointerParts)) {
                return $this->traverse($json[$pointerPart], $pointerParts);
            }
        } elseif (is_object($json) && in_array($pointerPart, array_keys(get_object_vars($json)))) {
            if (count($pointerParts) === 0) {
                return $json->{$pointerPart};
            }
            if ((is_object($json->{$pointerPart}) || is_array($json->{$pointerPart})) && is_array($pointerParts)) {
                return $this->traverse($json->{$pointerPart}, $pointerParts);
            }
        } elseif (is_object($json) && empty($pointerPart) && array_key_exists('_empty_', get_object_vars($json))) {
            $pointerPart = '_empty_';
            if (count($pointerParts) === 0) {
                return $json->{$pointerPart};
            }
            if ((is_object($json->{$pointerPart}) || is_array($json->{$pointerPart})) && is_array($pointerParts)) {
                return $this->traverse($json->{$pointerPart}, $pointerParts);
            }
        } elseif ($pointerPart === self::LAST_ARRAY_ELEMENT_CHAR && is_array($json)) {
            return end($json);
        } elseif (is_array($json) && count($json) < $pointerPart) {
            // Do nothing, let Exception bubble up
        } elseif (is_array($json) && array_key_exists($pointerPart, $json) && $json[$pointerPart] === null) {
            return $json[$pointerPart];
        }
        $exceptionMessage = sprintf(
            "Json Pointer '%s' references a nonexistent value",
            $this->getPointer()
        );
        throw new NonexistentValueReferencedException($exceptionMessage);
    }

    /**
     * @return string
     */
    public function getPointer()
    {
        return $this->pointer;
    }

    /**
     * @param array $pointerParts The Json Pointer parts to evaluate.
     *
     * @return array
     */
    private function evaluatePointerParts(array $pointerParts)
    {
        $searchables = ['~1', '~0'];
        $evaluations = ['/', '~'];

        $parts = [];
        array_filter($pointerParts, function ($v) use (&$parts, &$searchables, &$evaluations) {
            return $parts[] = str_replace($searchables, $evaluations, $v);
        });
        return $parts;
    }

    /**
     * @param $pointer
     * @param $value
     *
     * @return void
     * @throws \SoftInvest\Json\Pointer\InvalidPointerException
     * @throws \SoftInvest\Json\Pointer\NonexistentValueReferencedException
     */
    public function set($pointer, $value):void
    {
        if ($pointer === '') {
            return;
            // $output = json_encode($this->json, JSON_UNESCAPED_UNICODE);
            // workaround for https://bugs.php.net/bug.php?id=46600
            // return str_replace('"_empty_"', '""', $output);
        }

        $this->validatePointer($pointer);

        $this->pointer = $pointer;

        $plainPointerParts = array_slice(
            array_map('urldecode', explode('/', $pointer)),
            1
        );
        $this->traverset($this->json, $this->evaluatePointerParts($plainPointerParts), $value);
    }

    /**
     * @return ?array
     */
    public function toArray():?array
    {
        return is_string($this->json)?json_decode($this->json, true):$this->json;
    }

    /**
     * @return array|false|string
     */
    public function getJSON(): bool|array|string
    {
        return is_string($this->json)?$this->json:json_encode($this->json);
    }

    /**
     * @param $json
     * @param array $pointerParts
     * @param mixed $valueToSet
     *
     * @return array|mixed
     * @throws \SoftInvest\Json\Pointer\NonexistentValueReferencedException
     */
    private function traverset(&$json, array $pointerParts, mixed $valueToSet)
    {
        $pointerPart = array_shift($pointerParts);

        if (is_array($json) && isset($json[$pointerPart])) {
            if (count($pointerParts) === 0) {
                $json[$pointerPart] = $valueToSet;
                return $json;
            }
            if ((is_array($json[$pointerPart]) || is_object($json[$pointerPart])) && is_array($pointerParts)) {
                return $this->traverset($json[$pointerPart], $pointerParts, $valueToSet);
            }
        } elseif (is_object($json) && in_array($pointerPart, array_keys(get_object_vars($json)))) {
            if (count($pointerParts) === 0) {
                $json->{$pointerPart} = $valueToSet;
                return $json;
            }
            if ((is_object($json->{$pointerPart}) || is_array($json->{$pointerPart})) && is_array($pointerParts)) {
                return $this->traverset($json->{$pointerPart}, $pointerParts, $valueToSet);
            }
        } elseif (is_object($json) && empty($pointerPart) && array_key_exists('_empty_', get_object_vars($json))) {
            $pointerPart = '_empty_';
            if (count($pointerParts) === 0) {
                $json->{$pointerPart} = $valueToSet;
                return $json;
            }
            if ((is_object($json->{$pointerPart}) || is_array($json->{$pointerPart})) && is_array($pointerParts)) {
                return $this->traverset($json->{$pointerPart}, $pointerParts, $valueToSet);
            }
        } elseif ($pointerPart === self::LAST_ARRAY_ELEMENT_CHAR && is_array($json)) {
            $i = 0;
            $c = count($json);
            foreach ($json as $k => $v) {
                $i++;
                if ($i == $c) {
                    $json[$k] = $valueToSet;
                    break;
                }
            }
            return $json;
            //return end($json);
        } elseif (is_array($json) && count($json) < $pointerPart) {
            // Do nothing, let Exception bubble up
        } elseif (is_array($json) && array_key_exists($pointerPart, $json) && $json[$pointerPart] === null) {
            $json[$pointerPart] = $valueToSet;
            return $json;
        }
        $exceptionMessage = sprintf(
            "Json Pointer '%s' references a nonexistent value",
            $this->getPointer()
        );
        throw new NonexistentValueReferencedException($exceptionMessage);
    }
}
