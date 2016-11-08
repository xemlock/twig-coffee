<?php

use CoffeeScript\Lexer;

class TwigCoffee_TokenParser extends Twig_TokenParser
{
    public function __construct()
    {
        Lexer::init();
    }

    public function parse(Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        $variables = null;

        $options = array(
            'minify' => false,
            'bare'   => false,
        );

        // parse minify and bare attributes in either order
        while (true) {
            if ($stream->nextIf(Twig_Token::NAME_TYPE, 'minify')) {
                $options['minify'] = true;
            } elseif ($stream->nextIf(Twig_Token::NAME_TYPE, 'bare')) {
                $options['bare'] = true;
            } else {
                break;
            }
        }

        if ($stream->test(Twig_Token::NAME_TYPE, 'with')) {
            $variables = $this->parseWith($token);
        }

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        $contents = $this->parser->subparse(array($this, 'decideIfEnd'), true);

        if (!$contents instanceof Twig_Node_Text) {
            // if node is not a text node and has subnodes it means that it contains other tags
            if (count($contents)) {
                throw new Twig_Error_Syntax('CoffeeScript source must not contain any Twig tags', $contents->getTemplateLine(), $contents->getTemplateName());
            }
            $script = '';
        } else {
            $script = (string) $contents->getAttribute('data');
        }

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new TwigCoffee_Node($script, $options, $variables, $token->getLine(), $this->getTag());
    }

    /**
     * @param Twig_Token $token
     * @return Twig_Node_Expression_Array|Twig_Node_Expression_Name
     * @throws Exception
     * @throws Twig_Error_Syntax
     */
    public function parseWith(Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        // tests if current token is 'with', and moves to next one
        $stream->expect(Twig_Token::NAME_TYPE, 'with');

        if ($stream->test(Twig_Token::PUNCTUATION_TYPE, '{')) {
            return $this->parseInlineHash();
        } else {
            return $this->parseVariableName();
        }
    }

    public function getTag()
    {
        return 'coffee';
    }

    public function decideIfEnd(Twig_Token $token)
    {
        return $token->test(array('end' . $this->getTag()));
    }

    public function isValidVariableName($name)
    {
        if (!is_string($name) || !preg_match(Lexer::$IDENTIFIER, $name)) {
            return false;
        }
        if (in_array($name, Lexer::$COFFEE_RESERVED)) {
            return false;
        }
        return true;
    }

    /**
     * @return Twig_Node_Expression_Name
     * @throws Twig_Error_Syntax
     */
    public function parseVariableName()
    {
        $stream = $this->parser->getStream();

        $token = $stream->expect(Twig_Token::NAME_TYPE, null, 'Expected variable name');
        $value = $token->getValue();
        if (!$this->isValidVariableName($value)) {
            throw new Twig_Error_Syntax(sprintf('Invalid variable name "%s".', $value), $token->getLine(), $stream->getSourceContext()->getName());
        }
        $name = new Twig_Node_Expression_Name($value, $token->getLine());

        return $name;
    }

    /**
     * @return Twig_Node_Expression_Array
     * @throws Twig_Error_Syntax
     */
    public function parseInlineHash()
    {
        $hash = $this->parser->getExpressionParser()->parseHashExpression();

        // check if all hash keys are constant strings
        foreach ($hash as $index => $node) {
            /** @var Twig_Node $node */

            // skip value nodes
            if ($index % 2) {
                continue;
            }

            if (!$node instanceof Twig_Node_Expression_Constant) {
                throw new Twig_Error_Syntax('Only constant expressions can be used as variable names.', $node->getTemplateLine(), $node->getTemplateName());
            }

            $value = $node->getAttribute('value');
            if (!$this->isValidVariableName($value)) {
                throw new Twig_Error_Syntax(sprintf('Invalid variable name "%s".', $value), $node->getTemplateLine(), $node->getTemplateName());
            }
        }

        return $hash;
    }
}
