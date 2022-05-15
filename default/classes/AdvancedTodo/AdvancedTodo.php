<?php
namespace YY\Demo\AdvancedTodo;


use YY\System\Robot;

class AdvancedTodo extends Robot
{

	function __construct()
	{
		parent::__construct();

        //////////////////////////////
        //
        // Data will be arranged here
        //
        //////////////////////////////

        // A text of a new item

		$this['text'] = '';

        // All of the existing items

		$this['list'] = [];

        //////////////////////////////
        //
        // Visual representation
        //
        //////////////////////////////

        // This to-do-robot will be rendered inside a <div> element on the html page
        // You can adjust the div attributes such a way

		$this['attributes'] = [
            'class' => 'todoapp',
        ];

        // This will be included in page header. Only once.
        // Can be a plain string or array of strings or even recursive YY\Data tree containing strings.

        $this['include'] = '<link rel="stylesheet" href="/demo/todo/todo.css">';

    }

	function _PAINT()
	{
		?>
        <div class="well"><?= $this->TXT('To be implemented...') ?></div>
		<?php
	}

	function add() {
		$this['list'][] = $this['text'];
		$this['text'] = '';
	}

}
