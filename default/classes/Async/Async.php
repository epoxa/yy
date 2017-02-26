<?php


namespace YY\Demo\Async;

use Exception;
use YY\Core\Cache;
use YY\System\YY;
use YY\System\Robot;

class Async extends Robot
{

    function __construct()
    {
        parent::__construct([
            'runners' => [],
        ]);
        $this['include'] = "<script src='/demo/async/async.js'></script>";
        $this['runners'][] = new Runner([
            'amount' => 0,
        ]);
        $this['runners'][] = new Runner([
            'amount' => 0,
        ]);
        $this['runners'][] = new Runner([
            'amount' => 0,
        ]);
    }

    function _PAINT()
    {
        $total = 0;
        $speed = 0;
        foreach ($this['runners'] as $runner) {
            $total += $runner['amount'];
            if (isset($runner['PID'])) {
                $speed += $runner['speed'];
            }
            $runner->_SHOW();
        }
        $myHandle = YY::GetHandle($this);
        YY::clientExecute("reloadLater($myHandle)");
        ?>
        <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-5">
                        <?= $this->TXT('Total:', ['style' => ['font-size' => '200%']]) ?>
                    </div>
                    <div class="col-md-2 text-right">
                        <?= $this->TXT(['%2.2f', $speed / 100], ['style' => ['font-size' => '200%']]) ?>
                    </div>
                    <div class="col-md-5 text-right">
                        <strong>
                            <?= $this->TXT(['$&nbsp;%2.2f', $total / 100], ['style' => ['font-size' => '200%']]) ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

}
