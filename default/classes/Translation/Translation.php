<?php

namespace YY\Demo\Translation;


use YY\System\Robot;
use YY\System\YY;

class Translation extends Robot
{

    function __construct()
    {
        parent::__construct();
        $this['newLangName'] = '';
        $this['translateMode'] = isset(YY::$CURRENT_VIEW['TRANSLATOR']);
        if (!isset(YY::$WORLD['SYSTEM']['LANGUAGES'])) {
            YY::$WORLD['SYSTEM']['LANGUAGES'] = [];
        }
    }

    function _PAINT()
    {
        ?>
        <h3><?= $this->TXT('Current language') ?></h3>
        <div class="btn-group" role="group">
            <?= $this->drawLangButton(null, !isset(YY::$CURRENT_VIEW['LANGUAGE'])); ?>
            <?php foreach (YY::$WORLD['SYSTEM']['LANGUAGES'] as $lang => $dummy) : ?>
                <?= $this->drawLangButton($lang, isset(YY::$CURRENT_VIEW['LANGUAGE']) && YY::$CURRENT_VIEW['LANGUAGE'] === $lang); ?>
            <?php endforeach; ?>
        </div>
        <?php if (isset($this['add-lang-mode'])) : ?>
            <?= $this->INPUT('newLangName'); ?>
            <?= $this->CMD('Save', 'saveNewLang', [], ['class' => "btn btn-default"]); ?>
        <?php else: ?>
            <?= $this->CMD('Add New', 'addNewLang', [], ['class' => "btn btn-default"]); ?>
        <?php endif; ?>
        <br>
        <?php if (isset(YY::$CURRENT_VIEW['LANGUAGE'])) : ?>
            <?php if (!count(YY::$WORLD['SYSTEM']['LANGUAGES'][YY::$CURRENT_VIEW['LANGUAGE']])) : ?>
                <br>
                <div class="alert alert-danger">
                    <?= $this->TXT('Selected language does not have a translation at the moment.<br>You can translate any text by clicking red dot while "Translate mode" is on.') ?>
                </div>
            <?php endif; ?>
            <div class="checkbox">
            <?= $this->CHK('Translate mode', 'translateMode', 'switchTranslateMode') ?>
            </div>
        <?php endif; ?>
        <?php
    }

    private function drawLangButton($lang, $active)
    {
        return $this->CMD(
            $lang ?: 'Original', // Button caption
            'switchLang', ['lang' => $lang], // Handler
            ['class' => $active ? "btn btn-primary" : "btn btn-default"] // Visual style
        );
    }

    function switchLang($_param)
    {
        $lang = $_param['lang'];
        if ($lang) {
            YY::$CURRENT_VIEW['LANGUAGE'] = $lang;
            YY::$ME['selectedLanguage'] = $lang;
        } else {
            unset(
                YY::$CURRENT_VIEW['LANGUAGE'],
                YY::$CURRENT_VIEW['TRANSLATOR'],
                YY::$ME['selectedLanguage'],
                YY::$ME['translateMode'],
                $this['translateMode']
            );
        }
        $this->closeAddLang();
    }

    function addNewLang()
    {
        $this['add-lang-mode'] = true;
    }

    function saveNewLang()
    {
        $lang = trim($this['newLangName']);
        $this->closeAddLang();
        if (!$lang) return;
        if (!isset(YY::$WORLD['SYSTEM']['LANGUAGES'][$lang])) {
            YY::$WORLD['SYSTEM']['LANGUAGES'][$lang] = [];
            if (!$this['translateMode']) $this->switchTranslateMode();
        }
        YY::$CURRENT_VIEW['LANGUAGE'] = $lang;
        YY::$ME['selectedLanguage'] = $lang;
    }

    private function closeAddLang()
    {
        unset($this['add-lang-mode']);
        $this['newLangName'] = '';
    }

    function switchTranslateMode()
    {
        if ($this['translateMode']) {
            YY::$CURRENT_VIEW['TRANSLATOR'] = new Agent();
            YY::$ME['translateMode'] = true;
        } else {
            unset(YY::$CURRENT_VIEW['TRANSLATOR'], YY::$ME['translateMode']);
        }
    }

}
