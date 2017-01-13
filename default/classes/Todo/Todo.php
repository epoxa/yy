<?php
namespace YY\Demo\Todo;


use YY\System\YY;
use YY\System\Robot;

class Todo extends Robot
{

	function __construct()
	{
		parent::__construct();
		$this['text'] = '';
		$this['list'] = [];
		$this['include'] = '<link rel="stylesheet" href="/demo/todo/todo.css">';
		$this['attributes'] = ['class' => 'todoapp'];
	}

	function _PAINT()
	{
		?>
		<form onsubmit="go(<?= YY::GetHandle($this) ?>, 'add'); return false;">
			<?= YY::drawInput([], $this, 'text') ?>
			<?= YY::drawCommand(['class' => ['btn', 'btn-default', 'btn-small']], 'Add', $this, 'add') ?>
		</form>
		<?php foreach($this['list'] as $item) : ?>
		<div>
			<?= htmlspecialchars($item) ?>
		</div>
		<?php endforeach; ?>
		<?php
	}

	function add() {
		$this['list'][] = $this['text'];
		$this['text'] = '';
	}

}
