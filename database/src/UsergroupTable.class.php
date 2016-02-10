<?
namespace Database\Src;

class UsergroupTable extends \Database\Compilation\TableSrc
{
	function _construct()
	{
		$this->setName('user_group');
		$this->addAttribute('id', \Database::T_INTEGER, null, true, true, true);
		$this->addLink('user');
		$this->addLink('group');

	}
}
