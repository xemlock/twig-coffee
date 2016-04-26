<?php

class TwigCoffee_Minifier
{
    public static function minify($js)
    {
        if (class_exists('Patchwork\JSqueeze')) {
            $JSqueezeClass = 'Patchwork\JSqueeze';
            $minifier = new $JSqueezeClass();

            return $minifier->squeeze(
                $js,
                true, // $singleLine
                true, // $keepImportantComments
                false // $specialVarRx
            );
        }

        if (class_exists('JShrink\Minifier')) {
            $JShrinkMinifierClass = 'JShrink\Minifier';
            return $JShrinkMinifierClass::minify($js, array(
                'flaggedComments' => true, // keep flagged comments
            ));
        }

        if (class_exists('JSMinPlus')) {
            // suppress deprecated notice for 2.3
            return @JSMinPlus::minify($js);
        }

        throw new Exception('No JavaScript minifier detected');
    }
}
