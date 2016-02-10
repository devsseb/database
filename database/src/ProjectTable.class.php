<?
namespace Database\Src;

class ProjectTable extends \Database\Compilation\TableSrc
{
	function _construct()
	{

		$this->addAttribute('id', \Database::T_INTEGER, null, true, true, true);
		$this->addAttribute('name', \Database::T_CHAR);

		$this->addLink('company', \Database::R_1_N);
	}
}
