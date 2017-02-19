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
                'title' => 'To Do List',
                'description' => "A simple todo organizer. It does not utilize any external database engine or other kind of special data storage. All the data are stored inside of child robots as theirs plain properties.",
            ],
            'Translation' => [
                'title' => 'Translation',
                'description' => "You can translate all these demos by yourself just here in browser. To any language. Really!",
            ],
        ];
    }

    function _PAINT()
    {
        ?>

        <?= $this->CMD('reboot', 'reboot', [], ['style' => 'position: fixed; right: 0']) ?>

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

                <div class="container">
                    <div class="row">
                        <?php foreach ($this['examples'] as $class => $info) : ?>
                            <div class="col-md-4">
                                <h2><?= $this->TXT($info['title']) ?></h2>

                                <p><?= $this->TXT($info['description']) ?></p>

                                <p><?= $this->CMD('Open demo', 'run', ['class' => $class], ['class' => ['btn', 'btn-info'], 'role' => 'button']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else : ?>

                <div class="jumbotron text-center">
                    <div class="container">
                        <h1><?= $this->TXT($this['current']['title']) ?></h1>

                        <p><?= $this->TXT($this['current']['description']) ?></p>

                        <p><?= $this->CMD('Back to list', 'index', [], ['class' => ['btn', 'btn-info'], 'role' => 'button']) ?></p>
                    </div>
                </div>

                <?php $this['current']['robot']->_SHOW(); ?>

            <?php endif; ?>

            <hr>
            <footer class="text-center">
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
    }

    function index()
    {
        unset($this['current']);
    }

    function reboot()
    {
        unlink(DATA_DIR . 'DATA.db');
        YY::redirectUrl();
    }

}
