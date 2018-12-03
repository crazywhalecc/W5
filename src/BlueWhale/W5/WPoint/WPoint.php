<?php

namespace BlueWhale\W5\WPoint;

use BlueWhale\W5\W5;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\command\ConsoleCommandSender;

class WPoint
{
	
	public function __construct(W5 $plugin)
	{
		$this->plugin=$plugin;
		$this->user=new Config($plugin->getDataFolder()."userdata.yml",Config::YAML,array());
		$this->pointconfig=new Config($plugin->getDataFolder()."point-setting.yml",Config::YAML,array(
			"允许负值" => true,
			"最大值" => 1000000000
		));
	}
	public function createPlayerData($p)
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		else
			$p=strtolower($p);
		$array=array(
			"point" => 0,
			"win" => 0,
			"lose" => 0,
			"peace" => 0
		);
		$this->user->set($p,$array);
		$this->user->save(true);
		return true;
	}
	public function addStatus($p,$type)
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		else
			$p=strtolower($p);
		if(!$this->user->exists($p))
		{
			$this->createPlayerData($p);
		}
		$d=$this->user->get($p);
		switch($type)
		{
			case "win":
				$d["win"]++;
				break;
			case "lose":
				$d["lose"]++;
				break;
			case "peace":
				$d["peace"]++;
				break;
		}
		$this->user->set($p,$d);
		$this->user->save();
		return true;
	}
	public function getMyGrade($p)
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		else
			$p=strtolower($p);
		if(!$this->user->exists($p))
			return null;
		$list=[];
		foreach($this->user->getAll() as $pname=>$data)
		{
			$list[$pname]=$data["point"];
		}
		arsort($list);
		$i=1;
		foreach($list as $dataname=>$dat)
		{
			if($dataname == $p)
				break;
			else
				$i++;
		}
		return $i;
	}
	public function getAllPoint()
	{
		$list=[];
		foreach($this->user->getAll() as $pname=>$data)
		{
			$list[$pname]=$data["point"];
		}
		arsort($list);
		return $list;
	}
	public function getData($p)
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		else
			$p=strtolower($p);
		if(!$this->user->exists($p))
			return null;
		return $this->user->get($p);
	}
	public function getPoint($p)
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		elseif($p instanceof ConsoleCommandSender)
			return null;
		else
			$p=strtolower($p);
		if(!$this->user->exists($p))
			return null;
		return $this->user->get($p)["point"];
	}
	public function addPoint($p,$point = 0)
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		else
			$p=strtolower($p);
		if(!$this->user->exists($p))
		{
			$this->createPlayerData($p);
		}
		$d=$this->user->get($p);
		if(($d["point"]+$point) > $this->pointconfig->get("最大值"))
			return false;
		$d["point"]=$d["point"]+$point;
		$this->user->set($p,$d);
		$this->user->save(true);
		return true;
	}
	public function reducePoint($p,$point = 0)
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		else
			$p=strtolower($p);
		if(!$this->user->exists($p))
			return null;
		$d=$this->user->get($p);
		if(($d["point"]-$point) < 0)
		{
			if($this->pointconfig->get("允许负值") !== true)
			{
				return false;
			}
		}
		$d["point"]=$d["point"]-$point;
		$this->user->set($p,$d);
		$this->user->save(true);
		return true;
	}
}