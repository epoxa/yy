<?php


namespace YY\Demo\Async;


use YY\Core\Cache;
use YY\System\Robot;
use YY\System\YY;

class Runner extends Robot
{

    protected function _PAINT()
    {
        ?>
        <div class="panel panel-default" id="<?= $this['id'] ?>">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-1">
                        <div class="btn-group" role="group">
                            <?= $this->CMD(
                                [
                                    '' => isset($this['PID']) ?
                                        '<span class="glyphicon glyphicon-pause"></span>'
                                        : '<span class="glyphicon glyphicon-play"></span>',
                                ],
                                'toggle',
                                [
                                    'class' => isset($this['PID']) ?
                                        "btn btn-warning"
                                        : "btn btn-success",
                                ]) ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php if (isset($this['PID'])) : ?>
                            <?= $this->TXT(['Run for %d:%02d minutes<br>Memory: %d KB', floor($this['time'] / 60), $this['time'] % 60, $this['memory']]) ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 text-right">
                        <?php if (isset($this['PID'])) : ?>
                            <?= $this->TXT(['Average speed:<br>%2.2f&nbsp;$/sec', $this['speed'] / 100]) ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5 text-right">
                        <?= $this->TXT(['$&nbsp;%2.2f', $this['amount'] / 100], ['style' => ['font-size' => '200%']]) ?>
                    </div>
                </div>
                <?php if (isset($this['message'])) : ?>
                    <div>
                        <?= $this->TXT(['' => $this['message']], ['class' => "alert alert-warning alert-dismissable"]) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    function toggle()
    {
        if (isset($this['PID'])) {
            $this->stop();
        } else {
            $this->start();
        }
    }

    function start()
    {
        if (isset($this['PID'])) {
            if (posix_kill($this['PID'], 0)) {
                return; // Still running
            }
            unset($this['PID']);
        }
        unset($this['stop']);
        Cache::Flush();
        YY::Async($this, 'run');
    }

    function run()
    {
        $this['PID'] = getmypid();
        $this['startAmount'] = $this['amount'];
        $this['startTime'] = time();
        if (!pcntl_signal(SIGTERM, function ($sig, $siginfo) {
            $this['stop'] = true;
        })
        ) {
            return;
        }
        while (time() - $this['startTime'] < 1800 && !isset($this['stop'])) {
            $this->execute();
            Cache::Flush();
            if (!pcntl_signal_dispatch()) break;
        }
        unset($this['stop'], $this['PID']);
        $this['speed'] = 0;
    }

    function execute()
    {
        $this['amount'] = $this['amount'] + 1;
        $timeDelta = time() - $this['startTime'];
        $this['time'] = $timeDelta;
        if ($timeDelta) {
            $this['speed'] = ($this['amount'] - $this['startAmount']) / $timeDelta;
        } else {
            $this['speed'] = 0;
        }
        $this['memory'] = round(memory_get_usage(false) / 1024);
        $this['objects'] = Cache::GetCount();
    }

    function stop()
    {
        if (isset($this['PID'])) {
            exec('kill ' . $this['PID']);
        }
    }

}
