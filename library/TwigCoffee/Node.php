<?php

class TwigCoffee_Node extends Twig_Node
{
    /**
     * Constructor.
     *
     * @param string $script    CoffeeScript code
     * @param array  $options   An array of options for CoffeeScript compiler
     * @param int    $lineno    The line number corresponding to the occurrence of this Node
     * @param null   $tag       The tag name associated with the Node
     */
    public function __construct($script, array $options = array(), Twig_NodeInterface $variables = null, $lineno = 0, $tag = null)
    {
        $attributes = array(
            'script' => (string) $script,
            'bare'   => isset($options['bare']) && $options['bare'],
            'minify' => isset($options['minify']) && $options['minify'],
        );
        parent::__construct(array('variables' => $variables), $attributes, $lineno, $tag);
    }

    protected function extractVariableNames()
    {
        $variableNames = array();

        if ($this->hasNode('variables')) {
            $variables = $this->getNode('variables');
            if ($variables instanceof Twig_Node_Expression_Name) {
                $variableNames[$variables->getAttribute('name')] = null;
            } elseif ($variables instanceof Twig_Node_Expression_Array) {
                $prevNode = null;
                foreach ($variables as $index => $node) {
                    /** @var Twig_Node $node */
                    /** @var Twig_Node $prevNode */
                    if ($index % 2) {
                        $variableNames[$prevNode->getAttribute('value')] = $node;
                    }
                    $prevNode = $node;
                }
            }
        }

        return array_keys($variableNames);
    }

    /**
     * @param Twig_Compiler $compiler
     * @throws Exception
     */
    public function compile(Twig_Compiler $compiler)
    {
        $minify = 0&& $this->getAttribute('minify');
        $bare = $this->getAttribute('bare');

        $coffee = $this->getAttribute('script');
        $coffee = preg_replace('/^\s*<script([^>]*)>|<\/script>\s*$/i', '', $coffee);
        // $coffee = "(vars) ->\n  " . trim(str_replace("\n", "\n  ", $coffee));
        $coffee = trim($coffee);

        echo '[[', "\n", $coffee, "\n]]\n";

        // TODO at this point we must ensure that errors are not exceptions
        // especially 'Uninitialized string offset 0'
        $js = CoffeeScript\Compiler::compile($coffee, array(
            'bare' => true,
            'header' => false,
            'filename' => __FILE__,
        ));
        $js = trim($js, " \n\r\t;");

        if ($minify) {
            $js = TwigCoffee_Minifier::minify($js);
        }
        $js = '!' . $js; // expression mode

        print_r($this->extractVariableNames());

        // 1. Render opening SCRIPT tag and optional opening IIFE wrapper
        $compiler
            ->addDebugInfo($this)
            ->addIndentation()
            ->raw('echo ')
            ->string('<' . 'script>');
        if (!$bare) {
            $compiler
                ->raw(', ')
                ->string($minify ? '!function(){' : "\n!function() {\n");
        } elseif (!$minify) {
            $compiler
                ->raw(', ')
                ->string("\n");
        }
        $compiler->raw(";\n");

        // 2. Render passed variables
        // developer must ensure that keys in variables are valid JS identifiers
        $compiler
            ->addIndentation()
            ->raw('$variables = ');
        if ($this->getNode('variables')) {
            $compiler->subcompile($this->getNode('variables'));
        } else {
            $compiler->raw('null');
        }
        $compiler
            ->raw(";\n")
            ->addIndentation()
            ->raw("if (count(\$variables) && (is_array(\$variables) || \$variables instanceof Traversable)) {\n")
            ->indent();

        if ($minify) {
            $compiler
                ->addIndentation()->raw("\$first = true;\n")
                ->addIndentation()->raw("foreach (\$variables as \$key => \$value) {\n")
                    ->indent()
                    ->addIndentation()->raw("if (\$first) { \$first = false; echo 'var '; } else { echo ','; }\n")
                    ->addIndentation()->raw("echo \$key, '=', json_encode(\$value);\n")
                    ->outdent()
                ->addIndentation()->raw("}\n")
                ->addIndentation()->raw("echo ';';\n")
            ;
        } else {
            $compiler
                ->addIndentation()->raw("foreach (\$variables as \$key => \$value) {\n")
                    ->indent()
                    ->addIndentation()->raw("echo 'var ', \$key, ' = ', json_encode(\$value), ")->string(";\n")->raw(";\n")
                    ->outdent()
                ->addIndentation()->raw("}\n")
            ;
        }
        $compiler
            ->outdent()
            ->addIndentation()->raw("}\n")
        ;

        // 3. Render compiled code
        $compiler
            ->addIndentation()
            ->raw('echo ')
            ->string($js)
            ->raw(', ')->string('(')->raw(', json_encode($variables), ')->string(')')
        ;
        if (!$minify) {
            $compiler->raw(', ')->string("\n");
        }

        // 4. Render optional closing IIFE wrapper and closing SCRIPT tag
        if (!$bare) {
            $compiler
                ->raw(', ')
                ->string('}()' . ($minify ? '' : "\n"))
            ;
        }

        $compiler
            ->raw(', ')
            ->string('</' . 'script>')
            ->raw(";\n");
    }
}
