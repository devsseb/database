<?
namespace Database\Src;

class CategorieTable extends \Database\Compilation\TableSrc
{
	public function _construct()
	{

		$this->addAttribute('id', \Database::T_INTEGER, null, true, true, true);
		$this->addAttribute('name', \Database::T_CHAR);

		$this->addLink('categorie', \Database::R_1_N, 'parent_id', 'parent', 'id', 'children');

	}

}
