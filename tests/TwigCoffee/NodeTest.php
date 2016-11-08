<?php

class TwigCoffee_NodeTest extends PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $node = new TwigCoffee_Node('console.log "Hello world!"');

        $this->assertTrue($node->hasAttribute('script'));
        $this->assertEquals('console.log "Hello world!"', $node->getAttribute('script'));

        $this->assertTrue($node->hasAttribute('bare'));
        $this->assertFalse($node->getAttribute('bare'));

        $this->assertTrue($node->hasAttribute('minify'));
        $this->assertFalse($node->getAttribute('minify'));

        $node = new TwigCoffee_Node('console.log "Hello world!"', array('bare' => true));

        $this->assertTrue($node->getAttribute('bare'));
        $this->assertFalse($node->getAttribute('minify'));

        $node = new TwigCoffee_Node('console.log "Hello world!"', array('minify' => true));

        $this->assertFalse($node->getAttribute('bare'));
        $this->assertTrue($node->getAttribute('minify'));
    }
}
