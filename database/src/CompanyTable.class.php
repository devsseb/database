<?
namespace Database\Src;

class CompanyTable extends \Database\Compilation\TableSrc
{
	public function _construct()
	{

		$this->addAttribute('id', \Database::T_INTEGER, null, true, true, true);
		$this->addAttribute('name', \Database::T_CHAR);

	}

}
