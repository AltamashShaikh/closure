<?php
/**
 * Created by PhpStorm.
 * User: msarca
 * Date: 17.11.2016
 * Time: 13:38
 */

namespace Opis\Colibri\Test;

use Closure;
use stdClass;
use Serializable;
use Opis\Closure\ReflectionClosure;
use Opis\Closure\SerializableClosure;

class ClosureTest extends \PHPUnit_Framework_TestCase
{
    protected function s($closure, $bindThis = false)
    {
        if($closure instanceof Closure)
        {
            $closure = new SerializableClosure($closure, $bindThis);
        }

        return unserialize(serialize($closure))->getClosure();
    }

    public function testClosureUseReturnValue()
    {
        $a = 100;
        $c = function() use($a)
        {
            return $a;
        };

        $u = $this->s($c);

        $this->assertEquals($u(), $a);
    }

    public function testClosureUseReturnClosure()
    {
        $a = function($p){
            return $p + 1;
        };
        $b = function($p) use($a){
            return $a($p);
        };

        $v = 1;
        $u = $this->s($b);

        $this->assertEquals($v + 1, $u(1));
    }

    public function testClosureUseReturnClosureByRef()
    {
        $a = function($p){
            return $p + 1;
        };
        $b = function($p) use(&$a){
            return $a($p);
        };

        $v = 1;
        $u = $this->s($b);

        $this->assertEquals($v + 1, $u(1));
    }

    public function testClosureUseSelf()
    {

        $a = function() use (&$a){
            return $a;
        };
        $u = $this->s($a);

        $this->assertEquals($u, $u());
    }

    public function testClosureUseSelfInArray()
    {

        $a = array();

        $b = function() use(&$a){
            return $a[0];
        };

        $a[] = $b;

        $u = $this->s($b);

        $this->assertEquals($u, $u());
    }

    public function testClosureUseSelfInObject()
    {

        $a = new stdClass();

        $b = function() use(&$a){
            return $a->me;
        };

        $a->me = $b;

        $u = $this->s($b);

        $this->assertEquals($u, $u());
    }

    public function testClosureUseSelfInMultiArray()
    {

        $a = array();
        $x = null;

        $b = function() use(&$x){
            return $x;
        };

        $c = function($i) use (&$a) {
            $f = $a[$i];
            return $f();
        };

        $a[] = $b;
        $a[] = $c;
        $x = $c;

        $u = $this->s($c);

        $this->assertEquals($u, $u(0));
    }

    public function testClosureUseSelfInInstance()
    {
        $i = new ObjSelf();
        $c = function ($c) use($i){
            return $c === $i->o;
        };
        $i->o = $c;
        $u = $this->s($c);
        $this->assertTrue($u($u));
    }

    public function testClosureUseSelfInInstance2()
    {
        $i = new ObjSelf();
        $c = function () use($i, &$c){
            return $c === $i->o;
        };
        $i->o = $c;
        $u = $this->s($c);
        $this->assertTrue($u());
    }

    public function testClosureSerializationTwice()
    {
        $a = function($p){
            return $p;
        };

        $b = function($p) use($a){
            return $a($p);
        };

        $u = $this->s($this->s($b));

        $this->assertEquals('ok', $u('ok'));
    }

    public function testClosureRealSerialization()
    {
        $f = function($a, $b){
            return $a + $b;
        };

        $u = $this->s($this->s($f));
        $this->assertEquals(5, $u(2, 3));
    }

    public function testClosureObjectinObject()
    {
        $f = function() use (&$f) {
            return $f;
        };

        $t = new ObjnObj();
        $t->func = $f;

        $t2 = new ObjnObj();
        $t2->func = $f;

        $t->subtest = $t2;

        $x = unserialize(serialize($t));

        $g = $x->func;
        $g = $g();

        $ok = $x->func == $x->subtest->func;
        $ok = $ok && ($x->subtest->func == $g);

        $this->assertEquals(true, $ok);
    }

    public function testClosureNested()
    {
        $o = function($a) {

            // this should never happen
            if ($a === false) {
                return false;
            }

            $n = function ($b) {
                return !$b;
            };
            $ns = unserialize(serialize(new SerializableClosure($n)));

            return $ns(false);
        };

        $os = $this->s($o);

        $this->assertEquals(true, $os(true));
    }

    public function testClosureBindToObject()
    {
        $a = new A();

        $b = function(){
            return $this->aPublic();
        };

        $b = $b->bindTo($a, __NAMESPACE__ . "\\A");

        $u = $this->s($b);

        $this->assertEquals('public called', $u());
    }

    public function testClosureBindToObjectScope()
    {
        $a = new A();

        $b = function(){
            return $this->aProtected();
        };

        $b = $b->bindTo($a, __NAMESPACE__ . "\\A");

        $u = $this->s($b);

        $this->assertEquals('protected called', $u());
    }

    public function testClosureBindToObjectStaticScope()
    {
        $a = new A();

        $b = function(){
            return static::aStaticProtected();
        };

        $b = $b->bindTo(null, __NAMESPACE__ . "\\A");

        $u = $this->s($b);

        $this->assertEquals('static protected called', $u());
    }


    public function testClosureStatic()
    {
        $f = static function(){};
        $rc = new ReflectionClosure($f);
        $this->assertTrue($rc->isStatic());
    }

    public function testClosureStaticFail()
    {
        $f = static
            // This will not work
        function(){};
        $rc = new ReflectionClosure($f);
        $this->assertFalse($rc->isStatic());
    }
}

class ObjnObj implements Serializable {
    public $subtest;
    public $func;

    public function serialize() {

        SerializableClosure::enterContext();

        $object = serialize(array(
            'subtest' => $this->subtest,
            'func' => SerializableClosure::from($this->func),
        ));

        SerializableClosure::exitContext();

        return $object;
    }

    public function unserialize($data) {

        $data = unserialize($data);

        $this->subtest = $data['subtest'];
        $this->func = $data['func']->getClosure();
    }
}

class A
{
    protected static function aStaticProtected() {
        return 'static protected called';
    }

    protected function aProtected()
    {
        return 'protected called';
    }

    public function aPublic()
    {
        return 'public called';
    }
}


class ObjSelf
{
    public $o;
}
