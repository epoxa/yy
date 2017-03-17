<?php
namespace YY\Demo\Todo;


use YY\System\YY;
use YY\System\Robot;

class Todo extends Robot
{

	function __construct()
	{
		parent::__construct();

		$this['new-text'] = '';

		$this['list'] = [];
    }

	function _PAINT()
	{
		?>
        <div class="col-md-offset-3 col-md-6 col-sm-offset-2 col-sm-8">
            <form onsubmit="go(<?= YY::GetHandle($this) ?>, 'add'); return false;">
                <div class="input-group">
                    <?= $this->INPUT('new-text', ['class' => 'form-control']) ?>
                    <div class="input-group-btn">
                        <?= $this->CMD('Add', 'add', ['class' => ['btn', 'btn-primary']]) ?>
                    </div>
                </div>
            </form>

            <br>
            <div class="list-group">
                <?php foreach($this['list'] as $index => $item) : ?>
                    <?= $this->CMD(['' => $item], ['remove', 'index' => $index], ['class' => 'list-group-item']) ?>
                <?php endforeach; ?>
            </div>
        </div>
		<?php
	}

	function add() {
		$this['list'][] = $this['new-text'];
		$this['new-text'] = '';
        $this->focusInput('new-text');
	}

	function remove($_params) {
		unset($this['list'][$_params['index']]);
        $this->focusInput('new-text');
	}

}
