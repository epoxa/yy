<?php

namespace YY\Demo;

use YY\System\YY;
use YY\System\Robot;

class Demo extends Robot
{

    function __construct()
    {
        parent::__construct();
        $this['examples'] = [
            'Hello' => [
                'title' => 'Hello World',
                'description' => "This is a very basic example. Meet user interaction principles in YY.",
            ],
            'Todo' => [
                'title' => 'Simple To Do List',
                'description' => "A simple todo organizer. It does not utilize any external database engine or other kind of special data storage. All the data are stored inside of plain robot properties.",
            ],
            'AdvancedTodo' => [
                'title' => 'Advanced To Do List',
                'description' => "A bit more complicated todo manager. Consists of main application class and separate child robots for every todo item. See comments in source code.",
            ],
            'Translation' => [
                'title' => 'Translation',
                'description' => "You can translate all these demos by yourself just here in browser. To any language. Really!",
            ],
            'Async' => [
                'title' => 'Async Execution',
                'description' => "Do not wait for long time operations",
            ],
        ];

        $script = "$(document).keypress(function(e) {if (e.keyCode == 27) $('#show-index:visible').click() })";
        $this->includeAsset("<script>$script</script>");

        $this->includeAsset("<script src='/js/common.js'></script>");

        YY::clientExecute('ensureFocus()');

    }

    function _PAINT()
    {
        ?>

        <?php if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1') : ?>
            <?= $this->CMD('reboot', 'reboot', [], ['class' => 'yy-skip', 'style' => 'position: fixed; right: 0']) ?>
        <?php endif; ?>

        <div class="container">

            <?php if (empty($this['current'])) : ?>

                <div class="jumbotron text-center">
                    <div class="container">
                        <h1><?= $this->TXT('YY Demo') ?></h1>

                        <p><?= $this->TXT(['Hi, %s! Here are some examples of using YY engine.<br>You
                            can use any of these as a start point to build your own application.',
                                empty(YY::$ME['name']) ? 'stranger' : htmlspecialchars(YY::$ME['name']) ]) ?></p>
                    </div>
                </div>

                    <div style="display: flex; flex-wrap: wrap">
                        <?php foreach ($this['examples'] as $class => $info) : ?>
                            <div class="col-md-4 col-sm-6 col-xs-12" style="padding: 0.5cm">
                                <?= $this->CMD(
                                    $this->TXT($info['title'], ['before' => '<h3>', 'after' => '</h3>'])
                                    .
                                    $this->TXT($info['description'], ['style' => 'color: gray'])
                                    ,
                                    'run', ['class' => $class], ['class' => 'thumbnail', 'style' => 'height: 100%; padding: 0 0.5cm; text-decoration: none!important; color: black; width: 100%']
                                ) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

            <?php else : ?>

                <div class="jumbotron text-center">
                    <div class="container">
                        <h1><?= $this->TXT($this['current']['title']) ?></h1>

                        <p><?= $this->TXT($this['current']['description']) ?></p>

                        <p><?= $this->CMD('<kbd class="text-muted bg-primary small">Esc</kbd> &nbsp;Back to index', 'index', [], ['id' => 'show-index', 'class' => ['btn', 'btn-info', 'yy-skip'], 'role' => 'button']) ?></p>
                    </div>
                </div>

                <?php $this['current']['robot']->_SHOW(); ?>

            <?php endif; ?>


            <footer class="text-center col-md-12 col-sm-12">
                <hr>
                <p><?= $this->TXT(['&copy; 2016-%s epoxa', date('Y')]) ?></p>
            </footer>

        </div>

        <?php if (isset(YY::$CURRENT_VIEW['TRANSLATOR'])) : ?>

          <?php YY::$CURRENT_VIEW['TRANSLATOR']->_SHOW(); ?>

        <?php endif; ?>

        <?php
    }

    function run($_params)
    {
        $class = $_params['class'];
        $info = $this['examples'][$class];
        if (empty($info['robot'])) {
            $fullClass = "YY\\Demo\\$class\\$class";
            $demo = new $fullClass();
            $info['robot'] = $demo;
        }
        $this['current'] = $info;
        YY::clientExecute('ensureFocus()');
    }

    function index()
    {
        unset($this['current']);
        YY::clientExecute('ensureFocus()');
    }

    function reboot()
    {
        unlink(DATA_DIR . 'DATA.db');
        YY::redirectUrl();
    }

}
