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
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee %}
    square = (x) -> x * x
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

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
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee %}
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'bare'   => false,
                'minify' => false,
                'script' => '',
            ),
            $node
        );
    }

    public function testParseBare()
    {
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee bare %}
    Math.sin Math.PI
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

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
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee minify %}
    cube = (x) -> x * x * x
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

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
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee with foo %}
    console.log foo.bar
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

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
        $twig = $this->getTwigEnvironment();

        $twig->parse($twig->tokenize(<<<EOF
{% coffee with true %}
    console.log true
{% endcoffee %}
EOF
        ));
    }

    public function testParseWithInlineHash()
    {
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee with {foo: 'bar'} %}
    console.log foo
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

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
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee with {'foo': 'bar'} %}
    console.log foo
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

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
        $twig = $this->getTwigEnvironment();

        $twig->parse($twig->tokenize(<<<EOF
{% coffee with {true: 'false'} %}
    console.log true
{% endcoffee %}
EOF
        ));
    }

    /**
     * @expectedException Twig_Error_Syntax
     * @expectedExceptionMessage Invalid variable name "1"
     */
    public function testParseWithInlineHashNumericKey()
    {
        $twig = $this->getTwigEnvironment();

        $twig->parse($twig->tokenize(<<<EOF
{% coffee with {1: 1} %}
    console.log true
{% endcoffee %}
EOF
        ));
    }

    /**
     * @expectedException Twig_Error_Syntax
     * @expectedExceptionMessage Only constant expressions can be used as variable names
     */
    public function testParseWithInlineHashComputedKey()
    {
        $twig = $this->getTwigEnvironment();

        $twig->parse($twig->tokenize(<<<EOF
{% coffee with {('foo' ~ 'bar'): 'baz'} %}
    console.log true
{% endcoffee %}
EOF
        ));
    }
}
