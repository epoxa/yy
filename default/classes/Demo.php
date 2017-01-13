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
                'title' => 'To Do',
                'description' => "A simple todo organizer. It does not utilize any external database engine or other kind of special data storage. All the data are stored inside of child robots as theirs plain properties.",
            ],
        ];
    }

    function _PAINT()
    {
        ?>

        <div class="container">

            <?php if (empty($this['current'])) : ?>

                <div class="jumbotron text-center">
                    <div class="container">
                        <h1>YY Demo</h1>
                        <p>Hi, <?= empty(YY::$ME['name']) ?  'stranger' : htmlspecialchars(YY::$ME['name']) ?>! Here are some examples of using YY engine.<br>You can use any of these as a start point to build your own application.</p>
                    </div>
                </div>

                <div class="container">
                    <div class="row">
                        <?php foreach ($this['examples'] as $class => $info) : ?>
                            <div class="col-md-4">
                                <h2><?= $info['title'] ?></h2>
                                <p><?= $info['description'] ?></p>
                                <p><?= YY::drawCommand(['class' => ['btn', 'btn-info'], 'role' => 'button'], 'Open demo', $this, 'run', ['class' => $class]) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else : ?>

                <div class="jumbotron text-center">
                    <div class="container">
                        <h1><?= $this['current']['title'] ?></h1>
                        <p><?= $this['current']['description'] ?></p>
                        <p><?= YY::drawCommand(['class' => ['btn', 'btn-info'], 'role' => 'button'], 'Back to list', $this, 'index') ?></p>
                    </div>
                </div>

                <?php $this['current']['robot']->_SHOW(); ?>

            <?php endif; ?>

            <hr>
            <footer class="text-center">
                <p>&copy; 2016 epoxa</p>
            </footer>

        </div>

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

}
