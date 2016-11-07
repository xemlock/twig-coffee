<?php

class TwigCoffee_TokenParserTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Twig_Environment
     */
    protected $_twig;

    public function getTwigEnvironment()
    {
        if ($this->_twig === null) {
            $this->_twig = new Twig_Environment(null, array(
                'autoescape' => false,
                'optimizations' => 0,
            ));
            $this->_twig->addTokenParser(new TwigCoffee_TokenParser());
        }
        return $this->_twig;
    }

    /**
     * @param string $string
     * @return Twig_Node_Module
     */
    public function parseTwigString($string)
    {
        $twig = $this->getTwigEnvironment();
        return $twig->parse($twig->tokenize($string));
    }

    /**
     * Helper function for asserting structure of parsed node
     *
     * @param array $test
     * @param Twig_Node $node
     */
    public function assertCoffeeNode($test, Twig_Node $node)
    {
        $this->assertInstanceOf('TwigCoffee_Node', $node);

        foreach (array('minify', 'bare') as $attr) {
            $this->assertTrue($node->hasAttribute($attr));
            if (isset($test[$attr])) {
                $this->assertEquals($test[$attr], $node->getAttribute($attr));
            }
        }

        $this->assertTrue($node->hasAttribute('script'));
        if (isset($test['script'])) {
            $this->assertEquals(
                trim($test['script']),
                trim($node->getAttribute('script'))
            );
        }
    }

    public function testParse()
    {
        $node = $this->parseTwigString(<<<EOF
{% coffee %}
    square = (x) -> x * x
{% endcoffee %}
EOF
        )->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'bare'   => false,
                'minify' => false,
                'script' => 'square = (x) -> x * x',
            ),
            $node
        );
    }

    public function testParseEmpty()
    {
        $node = $this->parseTwigString(<<<EOF
{% coffee %}
{% endcoffee %}
EOF
        )->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'bare'   => false,
                'minify' => false,
                'script' => '',
            ),
            $node
        );
    }

    /**
     * @expectedException Twig_Error_Syntax
     * @expectedExceptionMessage CoffeeScript source must not contain any Twig tags
     */
    public function testParseTags()
    {
        $this->parseTwigString(<<<EOF
{% coffee %}
    console.log {{ 'foo' | json_encode }}
{% endcoffee %}
EOF
        );
    }

    public function testParseBare()
    {
        $node = $this->parseTwigString(<<<EOF
{% coffee bare %}
    Math.sin Math.PI
{% endcoffee %}
EOF
        )->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'bare'   => true,
                'script' => 'Math.sin Math.PI',
            ),
            $node
        );
    }

    public function testParseMinify()
    {
        $node = $this->parseTwigString(<<<EOF
{% coffee minify %}
    cube = (x) -> x * x * x
{% endcoffee %}
EOF
        )->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'minify' => true,
                'script' => 'cube = (x) -> x * x * x',
            ),
            $node
        );
    }

    public function testParseWithVariable()
    {
        $node = $this->parseTwigString(<<<EOF
{% coffee with foo %}
    console.log foo.bar
{% endcoffee %}
EOF
        )->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'script' => 'console.log foo.bar',
            ),
            $node
        );
        // print_r($node);
    }

    /**
     * @expectedException Twig_Error_Syntax
     * @expectedExceptionMessage Invalid variable name "true"
     */
    public function testParseWithKeywordVariable()
    {
        $this->parseTwigString(<<<EOF
{% coffee with true %}
    console.log true
{% endcoffee %}
EOF
        );
    }

    public function testParseWithInlineHash()
    {
        $node = $this->parseTwigString(<<<EOF
{% coffee with {foo: 'bar'} %}
    console.log foo
{% endcoffee %}
EOF
        )->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'script' => 'console.log foo',
            ),
            $node
        );
        // print_r($node);
    }

    public function testParseWithInlineHashQuotedKey()
    {
        $node = $this->parseTwigString(<<<EOF
{% coffee with {'foo': 'bar'} %}
    console.log foo
{% endcoffee %}
EOF
        )->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'script' => 'console.log foo',
            ),
            $node
        );
        // print_r($node);
    }

    /**
     * @expectedException Twig_Error_Syntax
     * @expectedExceptionMessage Invalid variable name "true"
     */
    public function testParseWithInlineHashKeywordKey()
    {
        $this->parseTwigString(<<<EOF
{% coffee with {true: 'false'} %}
    console.log true
{% endcoffee %}
EOF
        );
    }

    /**
     * @expectedException Twig_Error_Syntax
     * @expectedExceptionMessage Invalid variable name "1"
     */
    public function testParseWithInlineHashNumericKey()
    {
        $this->parseTwigString(<<<EOF
{% coffee with {1: 1} %}
    console.log true
{% endcoffee %}
EOF
        );
    }

    /**
     * @expectedException Twig_Error_Syntax
     * @expectedExceptionMessage Only constant expressions can be used as variable names
     */
    public function testParseWithInlineHashComputedKey()
    {
        $this->parseTwigString(<<<EOF
{% coffee with {('foo' ~ 'bar'): 'baz'} %}
    console.log true
{% endcoffee %}
EOF
        );
    }
}
