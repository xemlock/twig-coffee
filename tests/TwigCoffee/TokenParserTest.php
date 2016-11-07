<?php

class TwigCoffee_TokenParserTest extends PHPUnit_Framework_TestCase
{
    public function getTwigEnvironment()
    {
        $twig = new Twig_Environment(null, array(
            'autoescape' => false,
            'optimizations' => 0,
        ));
        $twig->addTokenParser(new TwigCoffee_TokenParser());
        return $twig;
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

        $this->assertTrue($node->hasAttribute('minify'));
        if (isset($test['minify'])) {
            $this->assertEquals($test['minify'], $node->getAttribute('minify'));
        }

        $this->assertTrue($node->hasNode('script'));
        $this->assertInstanceOf('Twig_Node_Text', $node->getNode('script'));

        if (isset($test['script'])) {
            $this->assertEquals(
                trim($test['script']),
                trim($node->getNode('script')->getAttribute('data'))
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
                'minify' => false,
                'script' => '',
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
                'minify' => false,
                'script' => 'console.log foo.bar',
            ),
            $node
        );
        // print_r($node);
    }

    /**
     * @expectedException Twig_Error_Syntax
     */
    public function testParseWithKeyword()
    {
        $twig = $this->getTwigEnvironment();

        $twig->parse($twig->tokenize(<<<EOF
{% coffee with true %}
    console.log true
{% endcoffee %}
EOF
        ));
    }

    public function testParseWithInlineDictionary()
    {return;
        $twig = $this->getTwigEnvironment();

        $node = $twig->parse($twig->tokenize(<<<EOF
{% coffee with {foo: 'bar'} %}
    console.log foo
{% endcoffee %}
EOF
        ))->getNode('body')->getNode(0);

        $this->assertCoffeeNode(
            array(
                'minify' => false,
                'script' => 'console.log foo',
            ),
            $node
        );
        // print_r($node);
    }
}
