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

    public function testCompile()
    {
        $compiler = new Twig_Compiler(
            $twig = new Twig_Environment(
                $this->getMockBuilder('Twig_LoaderInterface')->getMock(),
                array(
                    'autoescape' => false,
                    'optimizations' => 0,
                )
            )
        );
        $twig->addTokenParser(new TwigCoffee_TokenParser());

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee with {foo: 'bar'} %}
    console.log "Hello #{foo}"
{% endcoffee %}
EOF
        ));

        $result = $compiler->compile($node);
    }
}
