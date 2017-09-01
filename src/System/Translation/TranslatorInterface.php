<?php

namespace YY\System\Translation;

interface TranslatorInterface
{

    /**
     * @param $trace
     * @param $slug
     * @param $original
     * @param $attributes
     *
     * @return mixed
     *
     * May return new modified attributes for displaying html element
     */
    function registerTranslatable($trace, $slug, $original, $attributes);

}
