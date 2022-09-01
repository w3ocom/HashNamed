<?php

namespace Test\w3ocom\HashNamed;

use w3ocom\HashNamed\HashNamedInstallCode;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2022-08-30 at 10:05:59.
 */
class HashNamedInstallCodeTest extends HashNamedCoreTest {

    public string $tst_class = 'w3ocom\HashNamed\HashNamedInstallCode';
    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void {
        $local_cache_dir = "Tests/hash_named_cache";
        $rp = realpath($local_cache_dir . DIRECTORY_SEPARATOR);
        if (empty($rp) && !mkdir($local_cache_dir)) {
            throw new Exception("Can't create hash_named_cache directory for local-cache: $local_cache_dir");
        }
        $this->local_cache_dir = realpath($local_cache_dir);
        $this->object = new $this->tst_class($this->local_cache_dir);
    }

        
    public function prepareCodeProvider() {
        return [
//-----------------------------
'good_fn_prep' => [
    'php-function', // type
    'test',        // name
    'af86fc8f696281325f7dc723cb3ea82f3889ea8ee5c3bc5fa5a997e390e3c5b6', // hash
//code:
"
function test(\$a) {
    return \$a + 1;
}
",
    [], // expected-array
    ],
//-------------------------------
'good_class_prep' => [
    'php-class', // type
    'SuperTestClass', // name
    'ce79a51ec623e127a9fe1e3813f42c909474da34616f7424155365060bc9bc61', // hash
//code:
"
class SuperTestClass {
    public function test (\$a) {
        return \$a + 1;
    }
}
",
    [], // expected-array
    ],
//-------------------------------
// class with php-tag, hash is equal with previous case
'class_with_php_tag' => [
    'php-class', // type
    'SuperTestClass', // name
    'ce79a51ec623e127a9fe1e3813f42c909474da34616f7424155365060bc9bc61', // hash
//code:
"
<?php

class SuperTestClass {
    public function test (\$a) {
        return \$a + 1;
    }
}
",
    [], // expected-array
    ],
//-------------------------------
// class with namespace and php-tag
'class_with_namespace_and_php_tag' => [
    'php-class', // type
    'SuperTestClass', // name
    'fe48d8f77371b999577a7451cc5f1cebd965a292c5512da729eef941dd147baf', // hash
//code:
"
<?php
namespace xxx;
class SuperTestClass {
    public function test (\$a) {
        return \$a + 1;
    }
}
",
    [], // expected-array
    ],
//--------------------------------
// unsupported type case
'bad_type_case' => [
    'php-undefined',//type
    '', //name
    '', //hash
    '<?php class X { }', //code
    NULL // expected result
   ],
//--------------------------------
// code without body
'code_without_body' => [
    'php-function', //type
    '', //name
    '', //hash
    '<?php function();', //code
    NULL // expected result
   ],
//--------------------------------
// code without name
'code_without_name' => [
    'php-function',
    '', //name
    '', //hash
    '<?php function { /* NO NAME */ }', //code
    NULL // expected result
   ],
//-------------------------------
   ];        
}
    
    /**
     * @dataProvider prepareCodeProvider
     * @covers w3ocom\HashNamed\HashNamedInstallCode::prepareCode
     */
    public function testPrepareCode($type, $name, $hash, $code, $exp_arr) {
        $h_arr = $this->object->prepareCode($code, $type);
        if (is_array($exp_arr)) {
            $this->assertEquals($name, $h_arr['name']);
            $this->assertEquals($hash, $h_arr['hash']);
        } else {
            $this->assertNull($h_arr);
        }
    }

    /**
     * @covers w3ocom\HashNamed\HashNamedInstallCode::installHashNamedCode
     */
    public function testInstallHashNamedCode() {
        //installHashNamedCode(string $code, string $type = 'php-function', bool $save_hashnamed = true)
        $code = <<<'CODE'
function test($a) {
    return $a + 1;
}
CODE;
        $h_arr = $this->object->installHashNamedCode($code, 'php-function');
        $this->assertEquals('\fn_cf9a51c914fd6ef41e06ac4078f05373d000ee0b', $h_arr['call_name']);
        
        $this->expectException(\Exception::class);
        $this->object->installHashNamedCode("Bad functoin code {}");
    }

    /**
     * @covers w3ocom\HashNamed\HashNamedInstallCode::installFunction
     */
    public function testInstallFunction() {
        $code = <<<'CODE'
function test($a) {
    return $a + 1;
}
CODE;
        $result = $this->object->installFunction($code);
        $this->assertEquals('\fn_cf9a51c914fd6ef41e06ac4078f05373d000ee0b', $result['call_name']);
        
        $this->expectException(\Exception::class);
        $this->object->installFunction("Bad functoin code {}");
    }

}