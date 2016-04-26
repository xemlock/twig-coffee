<?php

class TwigCoffee_TokenParser extends Twig_TokenParser
{
    public function parse(Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        $variables = null;

        $attributes = array(
            'minify' => false,
            'bare'   => false,
        );

        // parse minify and bare attributes in either order
        while (true) {
            if ($stream->nextIf(Twig_Token::NAME_TYPE, 'minify')) {
                $attributes['minify'] = true;
            } elseif ($stream->nextIf(Twig_Token::NAME_TYPE, 'bare')) {
                $attributes['bare'] = true;
            } else {
                break;
            }
        }

        if ($stream->test(Twig_Token::NAME_TYPE, 'with')) {
            $variables = $this->parseWith($token);
        }

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        $script = $this->parser->subparse(array($this, 'decideIfEnd'), true);
        if (!$script->hasAttribute('data')) {
            throw new Twig_Error_Syntax('CoffeeScript source must not contain any Twig tags');
        }

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new TwigCoffee_Node(compact('script', 'variables'), $attributes, $token->getLine(), $this->getTag());
    }

    public function parseWith(Twig_Token $token)
    {
        // idea based on https://gist.github.com/xphere/5410937

        $stream = $this->parser->getStream();
        $lineno = $token->getLine();

        $this->assertCurrent(__METHOD__, Twig_Token::NAME_TYPE, 'with');
        $stream->next();

        if ($stream->test(Twig_Token::PUNCTUATION_TYPE, '{')) {
            return $this->parseInlineObject($lineno);

        } else {
            // 0: name, 1: block_end
            if ($stream->look(1)->getType() === Twig_Token::BLOCK_END_TYPE) {
                return $this->parseAssignmentTarget();

            } else {
                $expressionParser = $this->parser->getExpressionParser();
                $names = $expressionParser->parseAssignmentExpression();
                $stream->expect(Twig_Token::OPERATOR_TYPE, '=');
                $values = $expressionParser->parseMultitargetExpression();

                if (count($names) !== count($values)) {
                    throw new Twig_Error_Syntax(
                        'When using with, you must have the same number of variables and assignments.',
                        $stream->getCurrent()->getLine(), $stream->getFilename()
                    );
                }

                return new Twig_Node(array('names' => $names, 'values' => $values));
            }
        }
    }

    protected function parseInlineObject($lineno)
    {
        return $this->parser->getExpressionParser()->parseHashExpression();
    }

    public function getTag()
    {
        return 'coffee';
    }

    public function decideIfEnd(Twig_Token $token)
    {
        return $token->test(array('end' . $this->getTag()));
    }

    /**
     * @internal
     */
    public function assertCurrent($method, $type, $value)
    {
        $current = $this->parser->getStream()->getCurrent();

        if ($current->getType() !== $type || $current->getValue() !== $value) {
            throw new Exception(sprintf(
                '%s expects current token to be %s "%s", received %s "%s"',
                $method,
                Twig_Token::typeToEnglish($type),
                $value,
                Twig_Token::typeToEnglish($current->getType()),
                $current->getValue()
            ));
        }
    }

    public function parseAssignmentTarget()
    {
        $stream = $this->parser->getStream();
        $names = array();

        $token = $stream->expect(Twig_Token::NAME_TYPE, null, 'Only variables can be assigned to');
        $value = $token->getValue();
        if (in_array(strtolower($value), array('true', 'false', 'none', 'null'))) {
            throw new Twig_Error_Syntax(sprintf('You cannot assign a value to "%s".', $value), $token->getLine(), $stream->getSourceContext()->getName());
        }
        $names[] = new Twig_Node_Expression_AssignName($value, $token->getLine());

        return new Twig_Node($names);
    }
}