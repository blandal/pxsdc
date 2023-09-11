<?php
namespace App\Takeaways;
use Illuminate\Support\Arr;

trait BaseFactory{
	private $status 	= true;
	private $errmsg 	= [];
	public function __construct(){
		return $this;
	}

	//调用多维数组的参数使用双下划线
	public function __call($fun, $arg){
		// if(isset($this->args) && !empty($this->args)){
			Arr::set($this->args, str_replace('__', '.', $fun), $arg[0]);
		// }
		return $this;
	}

	public function getError(){
		return $this->errmsg;
	}
	private function seterr($msg){
		$this->status 	= false;
		$this->errmsg[]	= $msg;
		return false;
	}
}