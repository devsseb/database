<?
namespace Database\Src;

class UserTable extends \Database\Compilation\TableSrc
{
	public function _construct()
	{

		$this->addAttribute('id', \Database::T_INTEGER, null, true, true, true);
		$this->addAttribute('mail', \Database::T_CHAR);
		$this->addAttribute('name', \Database::T_CHAR);
		$this->addAttribute('password', \Database::T_CHAR);
		$this->addAttribute('active', \Database::T_CHAR);

		$this->addLink('project', \Database::R_1_N);
		$this->addLink('group', \Database::R_N_N, null, null, null, null, null, null, null, array(object(
			'name', 'access',
			'type', \Database::T_INTEGER
		)));

	}

}
