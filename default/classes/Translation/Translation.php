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
        if (!isset(YY::$ME['LANGUAGES'])) {
            YY::$ME['LANGUAGES'] = [];
            // TODO: Copy current system translations as initial set
        }
    }

    function _PAINT()
    {
        ?>
        <h3><?= $this->TXT('Current language') ?></h3>
        <div class="btn-group" role="group">
            <?= $this->drawLangButton(null, !isset(YY::$ME['LANGUAGE'])); ?>
            <?php foreach (YY::$ME['LANGUAGES'] as $lang => $dummy) : ?>
                <?= $this->drawLangButton($lang, isset(YY::$ME['LANGUAGE']) && YY::$ME['LANGUAGE'] === $lang); ?>
            <?php endforeach; ?>
        </div>
        <?php if (isset($this['add-lang-mode'])) : ?>
        <form class="col-md-2" style="display: inline-block; float: none; position: relative; top: 13px" onsubmit="$('#lang-ok').click(); return false;">
            <div class="input-group">
                <?= $this->INPUT('newLangName', null, ['class' => 'form-control']); ?>
                <div class="input-group-btn">
                    <?= $this->CMD('Save', 'saveNewLang', ['id' => 'lang-ok', 'class' => "btn btn-default"]); ?>
                </div>
            </div>
        </form>
        <?php else: ?>
            <?= $this->CMD('Add New', 'addNewLang', ['class' => "btn btn-default"]); ?>
        <?php endif; ?>
        <br>
        <?php if (isset(YY::$ME['LANGUAGE'])) : ?>
            <?php if (!count(YY::$ME['LANGUAGES'][YY::$ME['LANGUAGE']])) : ?>
                <br>
                <div class="alert alert-danger">
                    <?= $this->TXT('Selected language does not have a translation at the moment.<br>You can translate any text by clicking red dot while "Translate mode" is on.') ?>
                </div>
            <?php endif; ?>
            <div class="checkbox">
            <?= $this->CHK('Translate mode', 'translateMode', 'updateTranslateMode') ?>
            </div>
        <?php endif; ?>
        <?php
    }

    private function drawLangButton($lang, $active)
    {
        return $this->CMD(
            $lang ?: 'Original', // Button caption
            ['switchLang', 'lang' => $lang], // Handler
            ['class' => $active ? "btn btn-primary" : "btn btn-default"] // Visual style
        );
    }

    function switchLang($_param)
    {
        $lang = $_param['lang'];
        if ($lang) {
            YY::$ME['LANGUAGE'] = $lang;
            YY::$CURRENT_VIEW['TRANSLATION'] = YY::$ME['LANGUAGES'][YY::$ME['LANGUAGE']];
        } else {
            unset(
                YY::$ME['LANGUAGE'],
                YY::$CURRENT_VIEW['TRANSLATOR'],
                YY::$CURRENT_VIEW['TRANSLATION'],
                YY::$ME['translateMode']
            );
            $this['translateMode'] = false;
        }
        $this->closeAddLang();
    }

    function addNewLang()
    {
        $this['add-lang-mode'] = true;
        $this->focusControl('newLangName');
    }

    function saveNewLang()
    {
        $lang = trim($this['newLangName']);
        $this->closeAddLang();
        if (!$lang) return;
        if (!isset(YY::$ME['LANGUAGES'][$lang])) {
            YY::$ME['LANGUAGES'][$lang] = [];
            if (!$this['translateMode']) {
                $this['translateMode'] = true;
                $this->updateTranslateMode();
            }
        }
        YY::$CURRENT_VIEW['TRANSLATION'] = YY::$ME['LANGUAGES'][$lang];
        YY::$ME['LANGUAGE'] = $lang;
    }

    private function closeAddLang()
    {
        unset($this['add-lang-mode']);
        $this['newLangName'] = '';
    }

    function updateTranslateMode()
    {
        if ($this['translateMode']) {
            YY::$CURRENT_VIEW['TRANSLATOR'] = new Agent();
            YY::$ME['translateMode'] = true;
        } else {
            unset(YY::$CURRENT_VIEW['TRANSLATOR'], YY::$ME['translateMode']);
        }
    }

}
