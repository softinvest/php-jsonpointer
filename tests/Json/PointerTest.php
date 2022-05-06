<?php
declare(strict_types=1);

namespace Json;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use SoftInvest\Json\Pointer;
use SoftInvest\Json\Pointer\InvalidJsonException;
use SoftInvest\Json\Pointer\InvalidPointerException;
use SoftInvest\Json\Pointer\NonexistentValueReferencedException;
use SoftInvest\Json\Pointer\NonWalkableJsonException;
use stdClass;

class PointerTest extends TestCase
{
    /**
     * @dataProvider invalidJsonProvider
     * @test
     */
    public function constructShouldThrowExpectedExceptionWhenUsingInvalidJson($invalidJson)
    {
        $this->expectException(InvalidJsonException::class);
        $this->expectExceptionMessage('Cannot operate on invalid Json.');

        $jsonPointer = new Pointer($invalidJson);
    }

    /**
     * @test
     * @dataProvider invalidPointerCharProvider
     */
    public function getShouldThrowExpectedExceptionWhenPointerStartsWithInvalidPointerChar($invalidPointerChar)
    {
        $this->expectException(InvalidPointerException::class);
        $this->expectExceptionMessage('Pointer starts with invalid character');

        $jsonPointer = new Pointer('{"a": 1}');
        $jsonPointer->get($invalidPointerChar);
    }

    /**
     * @test
     * @dataProvider nonStringPointerProvider
     */
    public function getShouldThrowExpectedExceptionWhenPointerIsNotAString($nonStringPointer)
    {
        $this->expectException(InvalidPointerException::class);
        $this->expectExceptionMessage('Pointer is not a string');

        $jsonPointer = new Pointer('{"a": 1}');
        $jsonPointer->get($nonStringPointer);
    }

    /**
     * @dataProvider nonWalkableJsonProvider
     * @test
     */
    public function getShouldThrowExpectedExceptionWhenUsingNonWalkableJson($nonWalkableJson)
    {
        $this->expectException(NonWalkableJsonException::class);
        $this->expectExceptionMessage('Non walkable Json to point through');

        $jsonPointer = new Pointer($nonWalkableJson);
        $jsonPointer->get('/');
    }

    /**
     * @test
     */
    public function getShouldReturnGivenJsonWhenUsingOnlyARootPointer()
    {
        $givenJson = '{"status":["done","started","planned"]}';
        $jsonPointer = new Pointer($givenJson);
        $pointedJson = $jsonPointer->get('');

        $this->assertJsonStringEqualsJsonString(
            $givenJson,
            $pointedJson,
            'Unexpected mismatch between given and pointed Json'
        );
    }

    /**
     * @test
     */
    public function getShouldNotCastEmptyObjectsToArrays()
    {
        $givenJson = '{"foo":{"bar":{},"baz":"qux"}}';
        $jsonPointer = new Pointer($givenJson);
        $pointedJson = $jsonPointer->get('/foo');

        $this->assertTrue(($pointedJson instanceof stdClass));
        $this->assertTrue(($pointedJson->bar instanceof stdClass));
    }

    /**
     * @test
     */
    public function getShouldNotEscapeUnicode()
    {
        $givenJson = '{"status":["第","二","个"]}';
        $jsonPointer = new Pointer($givenJson);
        $pointedJson = $jsonPointer->get('');

        $this->assertEquals(
            $givenJson,
            $pointedJson,
            'Escaped unicode between given and pointed Json'
        );
    }

    /**
     * @test
     */
    public function getPointerShouldReturnFedPointer()
    {
        $givenJson = '{"status": ["done", "started", "planned"]}';
        $jsonPointer = new Pointer($givenJson);
        $pointer = '/status/1';
        $pointedJson = $jsonPointer->get($pointer);

        $this->assertEquals($pointer, $jsonPointer->getPointer());
    }

    /**
     * @test
     */
    public function getShouldReturnExpectedValueOfSecondElementBelowNamedPointer()
    {
        $givenJson = '{"status": ["done", "started", "planned"]}';
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals('started', $jsonPointer->get('/status/1'));
    }

    /**
     * @test
     */
    public function getShouldReturnExpectedValueOfFourthElement()
    {
        $givenJson = '["done", "started", "planned","pending","archived"]';
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals('pending', $jsonPointer->get('/3'));
    }

    /**
     * @test
     */
    public function getShouldReturnExpectedValueOfFourthElementWithNoEscapeUnicode()
    {
        $givenJson = '["done", "started", "planned","第二个","archived"]';
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals('第二个', $jsonPointer->get('/3'));
    }

    /**
     * @test
     * @dataProvider nonexistentValueProvider
     */
    public function getShouldThrowExpectedExceptionWhenNonexistentValueIsReferenced($givenJson, $givenPointer)
    {
        $this->expectException(NonexistentValueReferencedException::class);
        $this->expectExceptionMessage('Json Pointer');

        $jsonPointer = new Pointer($givenJson);
        $jsonPointer->get($givenPointer);
    }

    /**
     * @test
     */
    public function getShouldReturnNullAsAValidValue()
    {
        $givenJson = '{"a":{"b":null}}';
        $jsonPointer = new Pointer($givenJson);

        $this->assertNull($jsonPointer->get('/a/b'));
    }

    /**
     * @test
     */
    public function getShouldReturnExpectedValueOfSecondElementBelowDeepNamedPointer()
    {
        $givenJson = '{"categories":{"a":{"a1":{"a1a":["a1aa"],"a1b":["a1bb"]},"a2":["a2a","a2b"]}}}';
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals('a2b', $jsonPointer->get('/categories/a/a2/1'));
    }

    /**
     * @test
     */
    public function getShouldReturnExpectedValueOfPointerWithSlashInKey()
    {
        $givenJson = '{"test/foo.txt":{"size":1521,"description":"Text File"}}';
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals(1521, $jsonPointer->get('/test%2Ffoo.txt/size'));
    }

    /**
     * @test
     * @dataProvider lastArrayElementsTestDataProvider
     */
    public function getShouldReturnLastArrayElementWhenHypenIsGiven($testData)
    {
        $givenJson = $testData['given-json'];
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals(
            $testData['expected-element'],
            $jsonPointer->get($testData['given-pointer'])
        );
    }

    /**
     * @test
     */
    public function getShouldTraverseToObjectPropertiesAfterArrayIndex()
    {
        $givenJson = '{"foo": {"bar": {"baz": [ {"bar":"baz"}, {"bar":"qux"} ] }}}';
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals('baz', $jsonPointer->get('/foo/bar/baz/0/bar'));
        $this->assertEquals('qux', $jsonPointer->get('/foo/bar/baz/1/bar'));
    }

    /**
     * @test
     */
    public function referenceTokenGettingEvaluated()
    {
        $givenJson = '{"a/b/c": 1, "m~n": 8, "a": {"b": {"c": 12} } }';
        $jsonPointer = new Pointer($givenJson);

        $this->assertEquals(1, $jsonPointer->get('/a~1b~1c'));
        $this->assertEquals(8, $jsonPointer->get('/m~0n'));
        $this->assertEquals(12, $jsonPointer->get('/a/b/c'));
    }

    /**
     * @dataProvider specSpecialCaseProvider
     * @test
     */
    public function specialCasesFromSpecAreMatched($expectedValue, $pointer)
    {
        $givenJson = '{"foo":["bar","baz"],"":0,"a/b":1,"c%d":2,"e^f":3,"g|h":4,"k\"l":6," ":7,"m~n":8}';
        $jsonPointer = new Pointer($givenJson);

        $this->assertSame($expectedValue, $jsonPointer->get($pointer));
    }

    /**
     * @test
     */
    public function getShouldReturnEmptyJson()
    {
        $givenJson = $expectedValue = '[]';
        $jsonPointer = new Pointer($givenJson);

        $this->assertSame($expectedValue, $jsonPointer->get(''));
    }

    /**
     * @return array
     */
    public function invalidPointerCharProvider()
    {
        return [
            ['*'],
            ['#'],
        ];
    }

    /**
     * @return array
     */
    public function lastArrayElementsTestDataProvider()
    {
        return [
            [
                [
                    'given-json' => '{"categories":{"a":{"a1":{"a1a":["a1aa"],"a1b":["a1bb"]},"a2":["a2a","a2b"]}}}',
                    'expected-element' => 'a2b',
                    'given-pointer' => '/categories/a/a2/-'
                ]
            ],
            [
                [
                    'given-json' => '{"a2":["a2a","a2b","a2c"]}',
                    'expected-element' => 'a2c',
                    'given-pointer' => '/a2/-'
                ]
            ],
        ];
    }

    /**
     * @return array
     */
    public function specSpecialCaseProvider()
    {
        return [
            ['{"foo":["bar","baz"],"":0,"a\/b":1,"c%d":2,"e^f":3,"g|h":4,"k\"l":6," ":7,"m~n":8}', ''],
            [['bar', 'baz'], '/foo'],
            ['bar', '/foo/0'],
            [0, '/'],
            [1, '/a~1b'],
            [2, '/c%d'],
            [3, '/e^f'],
            [4, '/g|h'],
            [6, "/k\"l"],
            [7, '/ '],
            [8, '/m~0n'],
        ];
    }

    /**
     * @return array
     */
    public function nonexistentValueProvider()
    {
        return [
            ['["done", "started", "planned","pending","archived"]', '/6'],
            ['{"categories":{"a":{"a1":{"a1a":["a1aa"],"a1b":["a1bb"]},"a2":["a2a","a2b"]}}}', '/categories/b'],
            ['{"a":{"b":{"c":null}}}', '/a/b/d'],
            ['{"foo":"bar"}', '/foo/boo'],
        ];
    }

    /**
     * @return array
     */
    public function nonStringPointerProvider()
    {
        return [
            [[]],
            [15],
            [new ArrayObject()],
            [null],
        ];
    }

    /**
     * @return array
     */
    public function invalidJsonProvider()
    {
        return [
            ['['],
            ['{'],
            ['{}}'],
            ['{"Missing colon" null}'],
        ];
    }

    /**
     * @return array
     */
    public function nonWalkableJsonProvider()
    {
        return [
            ['6'],
            [15],
            ['null'],
        ];
    }
}
