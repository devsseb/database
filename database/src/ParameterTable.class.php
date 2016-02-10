<?
namespace Database\Src;

class ParameterTable extends \Database\Compilation\TableSrc
{
	function _construct()
	{

		$this->addAttribute('name', \Database::T_CHAR);
		$this->addAttribute('color', \Database::T_CHAR);

	}
}
