<?php

namespace YY\System;

use YY\Core\Data;

// Роботы обеспечивают интерфейс с другими сущностями (людьми, процессами)
// Может реагировать на внешние раздражители.
// Может отображать себя.
// В данный момент используется HTTP.
// Впоследствии нужно здесь сделать защиту от недостоверных, вредоносных данных.

class Robot extends Data
{

	public function __construct($init = null)
	{
		parent::__construct($init);
	}

	public function _delete()
	{
		YY::robotDeleting($this);
		parent::_delete();
	}

	public final function _SHOW()
	{
		YY::showRobot($this);
	}

	// При вызове этой функции можно генерировать ошибку при попытке записи в любое свойство любого объекта.
	// Никакого действия, только вывод объекта для пользователя!

	protected function _PAINT()
	{
	}

	// Функция может быть вызвана (прямо или через _SHOW дочерних роботов) только в процедуре _SHOW экземпляра наследника этого класса.
	// Задает континуацию, срабатывающую после окончания текущего метода и соответствующего ответа пользователя.

	public function HUMAN_COMMAND($visual, $htmlCaption, $method, $params = null)
	{
		return YY::drawCommand($visual, $htmlCaption, $this, $method, $params);
	}

	protected function HUMAN_TEXT($visual, $param_name, $object = null)
	{
		if ($object === null) $object = $this;
		return YY::drawInput($visual, $object, $param_name);
	}

	protected function MY_TEXT($visual, $htmlText)
	{
		return YY::drawText($visual, $htmlText);
	}

	public function LINK($visual, $htmlCaption, $params = null)
	{
		return YY::drawInternalLink($visual, $htmlCaption, $this, $params);
	}

	public function DOCUMENT($visual, $params = null)
	{
		return YY::drawDocument($visual, $this, $params);
	}

	// TODO: Можно добавить параметры, например, для возможности скачивать файл
	protected function FILE($visual, $param_name, $object = null)
	{
		if ($object === null) $object = $this;
		return YY::drawFile($visual, $object, $param_name);
	}

}
