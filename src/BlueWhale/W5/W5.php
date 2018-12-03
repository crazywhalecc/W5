<?php

namespace BlueWhale\W5;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\tile\Sign;
use BlueWhale\W5\Commands\MainCommand;
use BlueWhale\W5\Commands\UserCommand;
use BlueWhale\W5\WPoint\WPoint;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\command\ConsoleCommandSender;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\Block;
use BlueWhale\W5\scheduleTasks\CallbackTask;
use pocketmine\level\Position;
use pocketmine\utils\Utils;

class W5 extends PluginBase
{
	public static $obj=null;
	public $settingMode = [];
	public $gameStatus = [];
	const CONFIG_VERSION = 4;
	public $WTaskSetting = false;
	public $EconomyAPISetting = false;
	public $waitRecover = [];
	public $rejoinTick=[];
	public $activateUpdate = array("no","1.0.0");

	public function onLoad()
	{
		self::$obj=$this;
		$this->currentVersion=$this->getDescription()->getVersion();
	}
	public static function getInstance()//API
	{
		return self::$obj;
	}
	public function onEnable()//启动
	{
		@mkdir($this->getDataFolder());
		$this->makeConfig();//生成配置文件
		$this->registerSettings();//注册设置和class
		$this->resetGames();//重置所有游戏桌面
		$oldConfig=$this->config->get("Config-Version");
		if($oldConfig < self::CONFIG_VERSION)
		{
			$this->updateConfig($oldConfig);
			$this->getServer()->getLogger()->notice("正在升级配置文件...");
		}
		if($this->getServer()->getPluginManager()->getPlugin('WTask') !== null)
		{
			$this->WTaskSetting=true;
		}
		else
		{
			$this->getServer()->getLogger()->notice("检测到你没有安装WTask，无法使用积分兑换任务功能！");
			$this->WTaskSetting=false;
		}
		if($this->getServer()->getPluginManager()->getPlugin('EconomyAPI') === null)
		{
			$this->getServer()->getLogger()->notice("检测到你没有安装经济核心，无法使用检测金钱等功能！");
			$this->EconomyAPISetting=false;
		}
		else
			$this->EconomyAPISetting=true;
		$this->getServer()->getLogger()->info("§2成功启动五子棋, 有bug记得反馈鲸鱼哦~！");
	}
	public function updateConfig($old)//升级配置文件
	{
		switch($old)
		{
			case 1:
				foreach($this->game->getAll() as $gamename=>$data)
				{
					$data["胜利消息"]="§a恭喜你，以迅雷不及掩耳之势赢得了对战！";
					$data["失败消息"]="§a很遗憾，你输了哦~下次努力吧！";
					$this->game->set($gamename,$data);
				}
				$this->game->save();
				$this->config->set("Config-Version",self::CONFIG_VERSION);
				$this->config->save();
				$this->updateConfig(2);
				return true;
			case 2:
				foreach($this->game->getAll() as $gamename=>$data)
				{
					$data["失败指令"]=[];
					$data["限制加入"]=false;
					$this->game->set($gamename,$data);
				}
				$this->game->save();
				$this->config->set("Config-Version",self::CONFIG_VERSION);
				$this->config->save();
				$this->updateConfig(3);
				return true;
			case 3:
				$this->config->set("积分方法","有加有减");
				$this->config->set("Config-Version",self::CONFIG_VERSION);
				$this->config->save();
				return true;
			default:
				return true;
		}
	}
	public function createPlayerData($p)//创建玩家档案
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
	public function addStatus($p,$type)//添加战绩
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
	public function getAllPoint()//获取所有积分
	{
		$list=[];
		foreach($this->user->getAll() as $pname=>$data)
		{
			$list[$pname]=$data["point"];
		}
		arsort($list);
		return $list;
	}
	public function getAllWinTime()//获取所有胜利场数
	{
		$list=[];
		foreach($this->user->getAll() as $pname=>$data)
		{
			$list[$pname]=$data["win"];
		}
		arsort($list);
		return $list;
	}
	public function getMyGrade($p)//获取玩家的积分[排名]
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
	public function getData($p)//获取玩家数据
	{
		if($p instanceof Player)
			$p=strtolower($p->getName());
		else
			$p=strtolower($p);
		if(!$this->user->exists($p))
			return null;
		return $this->user->get($p);
	}
	public function getPoint($p)//获取玩家积分
	{
		//echo "正在检测玩家传入类型\n";
		if($p instanceof Player)
		{
			$p=strtolower($p->getName());
			//echo "玩家类型为 Player\n";
		}
		else
		{
			$p=strtolower($p);
			//echo "玩家类型为字符串\n";
		}
		if(!$this->user->exists($p))
		{
			//echo "玩家数据不存在于config！\n";
			return null;
		}
		$d=$this->user->get($p);
		//echo "成功返回数据！\n";
		return $d["point"];
	}
	public function addPoint($p,$point = 0)//添加积分
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
	public function reducePoint($p,$point = 0)//减少积分
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
	public function registerSettings()//注册设置和class
	{
		$commandMap=$this->getServer()->getCommandMap();
		$this->listenerClass=new EventListener($this);
		$this->mainCommand=new MainCommand($this);
		$this->userCommand=new UserCommand($this);
		$commandMap->register("W5",$this->mainCommand);
		$commandMap->register("W5",$this->userCommand);
		//$this->point=new WPoint($this);
	}
	public function makeConfig()
	{
		$this->command=new Config($this->getDataFolder()."commands.yml",Config::YAML,array(
			"MainCommand" => array(
				"command" => "w5",
				"permission" => "op",
				"description" => "§bW5五子棋设置"
			),
			"UserCommand" => array(
				"command" => "五子棋",
				"permission" => "true",
				"description" => "§bW5五子棋数据"
			)
		));
		$this->rewardMethod=new Config($this->getDataFolder()."reward.yml",Config::YAML,array(
			"有加有减" => array(
				"add" => 1,
				"reduce" => 1,
				"checkcount" => 0
			),
			"规定数量" => array(
				"add" => "follow",
				"reduce" => 0,
				"checkcount" => 15
			),
			"跟随数量" => array(
				"add" => "follow",
				"reduce" => "follow",
				"checkcount" => 0
			)
		));
		$this->game=new Config($this->getDataFolder()."games.yml",Config::YAML,array());
		$this->config=new Config($this->getDataFolder()."config.yml",Config::YAML,array(
			"Config-Version" => self::CONFIG_VERSION,
			"逃跑扣除积分" => 20,
			"积分方法" => "有加有减",
			"掉线超时" => 20,
			"防刷分" => true,
			"扣钱开关" => true,
			"WTask专属任务" => array(),
			"超时时间(秒)" => 100
		));
		$this->user=new Config($this->getDataFolder()."userdata.yml",Config::YAML,array());
		$this->pointconfig=new Config($this->getDataFolder()."point-setting.yml",Config::YAML,array(
			"允许负值" => true,
			"最大值" => 1000000000,
			"排行榜颜色" => "§b"
		));
	}
	public function resetGames()//Reset All games
	{
		foreach($this->game->getAll() as $gamename=>$data)
			$this->resetGame($gamename);
	}
	public function resetGame($gamename)//重置游戏
	{
		if(!isset($this->gameClass[$gamename]))
		{
			$this->gameClass[$gamename]=new Game($gamename,$this);
		}
		$data=$this->game->get($gamename);
		$this->gameStatus[$gamename]=array(
			"p1" => "",
			"p2" => "",
			"starttime" => time(),
			"endtime" => "",
			"lasttime" => time(),
			"status" => false,
			"should" => mt_rand(1,2),
			"tick" => null
		);
		$this->setSign($gamename,0);
		$pos1=explode(":",$data["pos1"]);
		$pos2=explode(":",$data["pos2"]);
		$level=$this->getServer()->getLevelByName($pos1[3]);
		if($level === null)
			return false;
		$baseBlock=explode("-",$data["棋盘"]);
		for($x=$pos1[0];$x<=$pos2[0];$x++)
		{
			for($z=$pos1[2];$z<=$pos2[2];$z++)
			{
				$level->setBlock(new Vector3($x,$pos1[1],$z),Block::get($baseBlock[0],$baseBlock[1]));
			}
		}
	}
	public function joinGame($gamename,$name,$team)//加入棋盘
	{
		foreach($this->gameStatus as $listname=>$pro)
		{
			if($pro["p1"] == $name || $pro["p2"] == $name)
			{
				if($listname != $gamename)
				{
					$this->getServer()->getPlayerExact($name)->sendMessage("§c你已经加入一个游戏了，不能再加入！");
					return true;
				}
			}
		}
		if($this->EconomyAPISetting === true)
			$money=EconomyAPI::getInstance()->myMoney($name);
		else
			$money=1000000;
		$wrong=$this->game->get($gamename);
		if($wrong["限制加入"] !== false)
		{
			if($money < $wrong["限制加入"])
			{
				$this->getServer()->getPlayerExact($name)->sendMessage("§c检测到你的金钱不够哦， 快去赚钱再来玩吧！");
				return true;
			}
			else
			{
				if($this->config->get("扣钱开关") == true)
				{
					if($this->EconomyAPISetting !== false)
					{
						EconomyAPI::getInstance()->reduceMoney($name,$wrong["限制加入"]);
					}
				}
			}
		}
		if($team==1)
		{
			if($this->gameStatus[$gamename]["p2"] == $name)
			{
				$this->gameStatus[$gamename]["p2"]="";
				$this->getServer()->getPlayerExact($name)->sendMessage("§a成功取消游戏准备状态！");
				return true;
			}
			$this->gameStatus[$gamename]["p1"]=$name;
			if($this->gameStatus[$gamename]["p2"] != "")
			{
				$this->startGame($gamename);
				return true;
			}
			$this->setSign($gamename,0);
			$this->getServer()->getPlayerExact($name)->sendMessage("§a成功加入游戏！请等待另一名玩家加入！");
			return true;
		}
		elseif($team == 2)
		{
			if($this->gameStatus[$gamename]["p1"] == $name)
			{
				$this->gameStatus[$gamename]["p1"]="";
				$this->setSign($gamename,0);
				$this->getServer()->getPlayerExact($name)->sendMessage("§a成功取消游戏准备状态！");
				return true;
			}
			$this->gameStatus[$gamename]["p2"]=$name;
			if($this->gameStatus[$gamename]["p1"] != "");
			{
				$this->startGame($gamename);
				return true;
			}
			$this->setSign($gamename,0);
			$this->getServer()->getPlayerExact($name)->sendMessage("§a成功加入游戏！请等待另一名玩家加入！");
			return true;
		}
	}
	public function startGame($gamename)//启动游戏
	{
		$this->gameStatus[$gamename]["starttime"]=time();
		$this->gameStatus[$gamename]["status"]=true;
		$this->gameStatus[$gamename]["lasttime"]=time();
		$p1=$this->getServer()->getPlayerExact($this->gameStatus[$gamename]["p1"]);
		$p2=$this->getServer()->getPlayerExact($this->gameStatus[$gamename]["p2"]);
		$this->setSign($gamename,1);
		$p1->sendMessage("§a***** 五子棋，开战！ *****");
		$p2->sendMessage("§a***** 五子棋，开战！ *****");
		$this->gameStatus[$gamename]["tick"]=$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"handleTicking"],[$gamename,$p1,$p2]),20);
		return true;
	}
	public function regretChess($gamename,$pos,$team)//悔棋（下个版本添加）
	{

	}
	public function getGamePos($gamename)//获取游戏棋盘传送坐标
	{
		$data=$this->game->get($gamename);
		$pos1=explode(":",$data["pos1"]);
		$pos2=explode(":",$data["pos2"]);
		$x=($pos1[0]+$pos2[0])/2;
		$y=$pos1[1]+1;
		$z=($pos1[2]+$pos2[2])/2;
		$level=$this->getServer()->getLevelByName($pos1[3]);
		$pos=new Position($x,$y,$z,$level);
		return $pos;
	}
	public function handleTicking($gamename,$p1,$p2)//计时器
	{
		$data=$this->game->get($gamename);
		$time=time()-$this->gameStatus[$gamename]["starttime"];
		if($this->gameStatus[$gamename]["should"] == 1)
			$player=$this->gameStatus[$gamename]["p1"];
		else
			$player=$this->gameStatus[$gamename]["p2"];
		$msg=$data["tip提示"];
		if($p1->isOnline())
			$p1->sendTip(str_replace(["%n","{player}","{time}"],["\n",$player,$time],$msg));
		if($p2->isOnline())
			$p2->sendTip(str_replace(["%n","{player}","{time}"],["\n",$player,$time],$msg));
		if(!$p1->isOnline() && !$p2->isOnline())
		{
			$this->forceStopGame($gamename);
			return false;
		}
		$lasttime=$this->gameStatus[$gamename]["lasttime"];
		if((time() - $lasttime) >= $this->config->get("超时时间(秒)"))
		{
			$p1->sendMessage("§c** 五子棋，游戏超时！ **");
			$p2->sendMessage("§c** 五子棋，游戏超时！ **");
			$this->forceStopGame($gamename);
		}
	}
	public function isInPlate($gamename,$pos)//检查是否在棋盘内
	{
		$data=$this->game->get($gamename);
		$pos1=explode(":",$data["pos1"]);
		$pos2=explode(":",$data["pos2"]);
		if($pos->x >= $pos1[0] && $pos->x <= $pos2[0] && $pos->y == $pos1[1] && $pos->z >= $pos1[2] && $pos->z <= $pos2[2])
		{
			return true;
		}
		elseif($pos->x >= $pos1[0] && $pos->x <= $pos2[0] && $pos->y > $pos1[1] && $pos->y <= ($pos1[1] +2) && $pos->z >= $pos1[2] && $pos->z <= $pos2[2])
			return "up";
		else
			return false;
	}
	public function updateBlock($gamename,$pos,$team)//设置方块（更新棋子）
	{
		$data=$this->game->get($gamename);
		$team1block=explode("-",$data["白棋"]);
		$team2block=explode("-",$data["黑棋"]);
		$pos1=explode(":",$data["pos1"]);
		$pos2=explode(":",$data["pos2"]);
		$level=$this->getServer()->getLevelByName($pos1[3]);
		if($team == 1)
		{
			$level->setBlock($pos,Block::get($team1block[0],$team1block[1]));
			$this->gameStatus[$gamename]["should"]=2;
			$this->gameStatus[$gamename]["lasttime"]=time();
			return $this->check5($gamename);
		}
		elseif($team == 2)
		{
			$level->setBlock($pos,Block::get($team2block[0],$team2block[1]));
			$this->gameStatus[$gamename]["should"]=1;
			$this->gameStatus[$gamename]["lasttime"]=time();
			return $this->check5($gamename);
		}
		return true;
	}
	public function getChessCount($gamename)//返回棋子数
	{
		$data=$this->game->get($gamename);
		$team1block=explode("-",$data["白棋"]);
		$team2block=explode("-",$data["黑棋"]);
		$pos1=explode(":",$data["pos1"]);
		$pos2=explode(":",$data["pos2"]);
		$t1count=0;$t2count=0;
		$level=$this->getServer()->getLevelByName($pos1[3]);
		for($x=$pos1[0];$x<=$pos2[0];$x++)
		{
			for($z=$pos1[2];$z<=$pos2[2];$z++)
			{
				if($level->getBlock(new Vector3($x,$pos1[1],$z))->getId() == $team1block[0] && $level->getBlock(new Vector3($x,$pos1[1],$z))->getDamage() == $team1block[1])
					$t1count++;
				if($level->getBlock(new Vector3($x,$pos1[1],$z))->getId() == $team2block[0] && $level->getBlock(new Vector3($x,$pos1[1],$z))->getDamage() == $team2block[1])
					$t2count++;
			}
		}
		return array($t1count,$t2count);
	}
	public function check5($gamename,$pos = null)//检查五子
	{
		$data=$this->game->get($gamename);
		$pos1=explode(":",$data["pos1"]);
		$pos2=explode(":",$data["pos2"]);
		$team1block=$data["白棋"];
		$team2block=$data["黑棋"];
		$level=$this->getServer()->getLevelByName($pos1[3]);
		$win=false;$winteam=0;
		for($x=$pos1[0];$x<=$pos2[0];$x++)
		{
			for($z=$pos1[2];$z<=$pos2[2];$z++)
			{
				$block=$level->getBlock(new Vector3($x,$pos1[1],$z))->getId()."-".$level->getBlock(new Vector3($x,$pos1[1],$z))->getDamage();
				if($block == $team1block)// || $block == $team2block)
				{
					$concentrate=0;
					for($xi=$x;$xi<=$x+4;$xi++)
					{
						$blocki=$level->getBlock(new Vector3($xi,$pos1[1],$z))->getId()."-".$level->getBlock(new Vector3($xi,$pos1[1],$z))->getDamage();
						if($blocki == $team1block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=1;
						break;
					}
					unset($xi);
					$concentrate=0;
					for($zi=$z;$zi<=$z+4;$zi++)
					{
						$blocki=$level->getBlock(new Vector3($x,$pos1[1],$zi))->getId()."-".$level->getBlock(new Vector3($x,$pos1[1],$zi))->getDamage();
						if($blocki == $team1block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=1;
						break;
					}
					unset($zi);
					$concentrate=0;
					for($xzi=$x;$xzi<=$x+4;$xzi++)
					{
						$xp=$xzi-$x;
						$blocki=$level->getBlock(new Vector3($xzi,$pos1[1],$z+$xp))->getId()."-".$level->getBlock(new Vector3($xzi,$pos1[1],$z+$xp))->getDamage();
						if($blocki == $team1block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=1;
						break;
					}
					unset($xzi);
					$concentrate=0;
					for($xzi=$x;$xzi<=$x+4;$xzi++)
					{
						$xp=$xzi-$x;
						$blocki=$level->getBlock(new Vector3($xzi,$pos1[1],$z-$xp))->getId()."-".$level->getBlock(new Vector3($xzi,$pos1[1],$z-$xp))->getDamage();
						if($blocki == $team1block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=1;
						break;
					}
					unset($xzi);
				}
				elseif($block == $team2block)
				{
					$concentrate=0;
					for($xi=$x;$xi<=$x+4;$xi++)
					{
						$blocki=$level->getBlock(new Vector3($xi,$pos1[1],$z))->getId()."-".$level->getBlock(new Vector3($xi,$pos1[1],$z))->getDamage();
						if($blocki == $team2block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=2;
						break;
					}
					unset($xi);
					$concentrate=0;
					for($zi=$z;$zi<=$z+4;$zi++)
					{
						$blocki=$level->getBlock(new Vector3($x,$pos1[1],$zi))->getId()."-".$level->getBlock(new Vector3($x,$pos1[1],$zi))->getDamage();
						if($blocki == $team2block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=2;
						break;
					}
					unset($zi);
					$concentrate=0;
					for($xzi=$x;$xzi<=$x+4;$xzi++)
					{
						$xp=$xzi-$x;
						$blocki=$level->getBlock(new Vector3($xzi,$pos1[1],$z+$xp))->getId()."-".$level->getBlock(new Vector3($xzi,$pos1[1],$z+$xp))->getDamage();
						if($blocki == $team2block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=2;
						break;
					}
					unset($xzi);
					$concentrate=0;
					for($xzi=$x;$xzi<=$x+4;$xzi++)
					{
						$xp=$xzi-$x;
						$blocki=$level->getBlock(new Vector3($xzi,$pos1[1],$z-$xp))->getId()."-".$level->getBlock(new Vector3($xzi,$pos1[1],$z-$xp))->getDamage();
						if($blocki == $team2block)// || $blocki == $team2block)
						{
							$concentrate++;
						}
						else
							continue;
					}
					if($concentrate == 5)
					{
						$win=true;
						$winteam=2;
						break;
					}
					unset($xzi);
				}
				else
					continue;
				unset($block);
				//unset($z);
			}
			if($win === true)
				break;
		}
		//unset($x);
		if($win !== true)
		{
			unset($data,$pos1,$pos2,$team1block,$team2block,$level,$win,$winteam);
			return true;
		}
		else
		{
			$this->congratulatePlayer($gamename,$winteam);
			unset($data,$pos1,$pos2,$team1block,$team2block,$level,$win,$winteam);
			$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"forceStopGame"],[$gamename]),20);
			return true;
		}
	}
	public function congratulatePlayer($gamename,$winteam)
	{
		$team=($winteam == 1 ? "白方" : "黑方");
		$p1=$this->getServer()->getPlayerExact($this->gameStatus[$gamename]["p1"]);
		$p2=$this->getServer()->getPlayerExact($this->gameStatus[$gamename]["p2"]);
		if($this->config->get("防刷分") === true)
		{
			if($p1->getAddress() == $p2->getAddress())
			{
				$p1->sendMessage("§c系统开启了防刷分功能，同一网络下无法获取积分！");
				$p2->sendMessage("§c系统开启了防刷分功能，同一网络下无法获取积分！");
				return true;
			}
		}
		$data=$this->game->get($gamename);
		$pname=($winteam == 1 ? $p1->getName() : $p2->getName());
		$winmsg=$data["胜利消息"];
		$failmsg=$data["失败消息"];
		if($winteam == 1)
		{
			$p1->sendMessage(str_replace(["{team}","{player}"],[$team,$pname],$winmsg));
			$p2->sendMessage(str_replace(["{team}","{player}"],[$team,$pname],$failmsg));
			foreach($data["胜利指令"] as $cmd)
			{
				$cmda=str_replace("%p",$p1->getName(),$cmd);
				$this->getServer()->dispatchCommand(new ConsoleCommandSender(),$cmda);
			}
			foreach($data["失败指令"] as $cmd)
			{
				$cmda=str_replace("%p",$p2->getName(),$cmd);
				$this->getServer()->dispatchCommand(new ConsoleCommandSender(),$cmda);
			}
			//$count=$this->getChessCount($gamename);
			$this->rewardPoint($gamename,$winteam);
		}
		else
		{
			$p2->sendMessage(str_replace(["{team}","{player}"],[$team,$pname],$winmsg));
			$p1->sendMessage(str_replace(["{team}","{player}"],[$team,$pname],$failmsg));
			foreach($data["胜利指令"] as $cmd)
			{
				$cmda=str_replace("%p",$p2->getName(),$cmd);
				$this->getServer()->dispatchCommand(new ConsoleCommandSender(),$cmda);
			}
			foreach($data["失败指令"] as $cmd)
			{
				$cmda=str_replace("%p",$p1->getName(),$cmd);
				$this->getServer()->dispatchCommand(new ConsoleCommandSender(),$cmda);
			}
			//$count=$this->getChessCount($gamename);
			$this->rewardPoint($gamename,$winteam);
		}
		return true;
	}
	public function rewardPoint($gamename,$winteam)
	{
		$p[0]=null;
		$p[1]=$this->getServer()->getPlayerExact($this->gameStatus[$gamename]["p1"]);
		$p[2]=$this->getServer()->getPlayerExact($this->gameStatus[$gamename]["p2"]);
		$count=$this->getChessCount($gamename);
		$this->addStatus($p[$winteam],"win");
		$this->addStatus($p[($winteam == 1 ? 2 : 1)],"lose");
		$method=$this->config->get("积分方法");
		if($method === null)
			return false;
		$getMethod=$this->rewardMethod->get($method);
		$checkcount=$getMethod["checkcount"];
		if($getMethod["add"] == "follow")
		{
			if($count[$winteam-1] >= $checkcount)
				$this->addPoint($p[$winteam],$count[$winteam-1]-$checkcount);
		}
		else
		{
			$this->addPoint($p[$winteam],$getMethod["add"]);
		}
		if($getMethod["reduce"] == "follow")
		{
			if($count[($winteam == 1 ? 2 : 1)-1] >= $checkcount)
				$this->reducePoint($p[($winteam == 1 ? 2 : 1)],$count[($winteam == 1 ? 2 : 1)-1]-$checkcount);
		}
		else
		{
			$this->reducePoint($p[($winteam == 1 ? 2 : 1)],$getMethod["reduce"]);
		}
		/*$method=explode(";",$method);
		$winway=explode(",",$method[0]);
		$loseway=explode(",",$method[1]);
		foreach($winway as $way1)
		{
			$ways=explode("|",$way1);
			switch($ways[0])
			{
				case "减分":
					$this->reducePoint($p[$winteam],$ways[1]);
					break;
				case "none":
					break;
				case "加分":
					$this->addPoint($p[$winteam],$ways[1]);
					break;
				case "检查数量":
					$counts=$ways[1];
					if($count[$winteam-1] < $counts)
					{
						$
					}
			}
		}
		switch($this->config->get("积分方法"))
		{
			case 1:
				if($count[$winteam-1] >= 10)
					$score=$count[$winteam-1]-10;
				else
					$score=0;
				$this->addPoint($p[$winteam],$score);
				$this->addStatus($p[$winteam],"win");
				$this->addStatus($p[($winteam == 1 ? 2 : 1)],"lose");
				break;
			case 2:
				if($count[$winteam-1] >= 10)
					$score=$count[$winteam-1]-10;
				else
					$score=0;
				$this->addPoint($p[$winteam],$score);
				$reducescore=(int)$score/2;
				$this->reducePoint($p[($winteam == 1 ? 2 : 1)],$reducescore);
				$this->addStatus($p[$winteam],"win");
				$this->addStatus($p[($winteam == 1 ? 2 : 1)],"lose");
				break;
		}*/
		return true;
	}
	public function forceStopGame($gamename)
	{
		$this->gameStatus[$gamename]["tick"]->remove();
		$this->resetGame($gamename);
		return true;
	}
	public function setSign($gamename,$type)
	{
		switch($type)
		{
			case 0:
				$data=$this->game->get($gamename);
				$sign=explode(":",$data["牌子坐标"]);
				$level=$this->getServer()->getLevelByName($sign[3]);
				if($level === null)
					return false;
				$tile=$level->getTile(new Vector3($sign[0],$sign[1],$sign[2]));
				if(!$tile instanceof Sign)
				{
					echo "sign的tile错误！\n";
					return false;
				}
				$current=$this->replaceData($gamename,$data["空闲牌子"]);
				$tile->setText($current[0],$current[1],$current[2],$current[3]);
				return true;
			case 1:
				$data=$this->game->get($gamename);
				$sign=explode(":",$data["牌子坐标"]);
				$level=$this->getServer()->getLevelByName($sign[3]);
				if($level === null)
					return false;
				$tile=$level->getTile(new Vector3($sign[0],$sign[1],$sign[2]));
				if(!$tile instanceof Sign)
				{
					echo "sign的tile错误！\n";
					return false;
				}
				$current=$this->replaceData($gamename,$data["游戏牌子"]);
				$tile->setText($current[0],$current[1],$current[2],$current[3]);
				return true;
		}
	}
	public function replaceData($gamename,$signData)
	{
		foreach($signData as $ID=>$data)
		{
			$data=str_replace("{p1}",($this->gameStatus[$gamename]["p1"] == "" ? "无" : $this->gameStatus[$gamename]["p1"]),$data);
			$line[$ID]=str_replace("{p2}",($this->gameStatus[$gamename]["p2"] == "" ? "无" : $this->gameStatus[$gamename]["p2"]),$data);
			unset($ID,$data);
		}
		return $line;
	}
	public function waitRejoin($gamename,$p,$team)
	{
		$this->getServer()->getPlayerExact($this->gameStatus[$gamename]["p".$team]);
		$this->forceStopGame($gamename);
		$this->reducePoint($p,$this->config->get("逃跑扣除积分"));
		return true;
	}
	public function createGame($playername)
	{
		if(isset($this->settingMode[$playername]))
		{
			$data=$this->settingMode[$playername];
			unset($this->settingMode[$playername]);
			$pos1x=($data["pos1"][0] > $data["pos2"][0] ? $data["pos2"][0] : $data["pos1"][0]);
			$pos1y=($data["pos1"][1] > $data["pos2"][1] ? $data["pos2"][1] : $data["pos1"][1]);
			$pos1z=($data["pos1"][2] > $data["pos2"][2] ? $data["pos2"][2] : $data["pos1"][2]);
			$pos2x=($data["pos1"][0] <= $data["pos2"][0] ? $data["pos2"][0] : $data["pos1"][0]);
			$pos2y=($data["pos1"][1] <= $data["pos2"][1] ? $data["pos2"][1] : $data["pos1"][1]);
			$pos2z=($data["pos1"][2] <= $data["pos2"][2] ? $data["pos2"][2] : $data["pos1"][2]);
			$level=$data["pos1"][3];
			$array=array(
				"pos1" => $pos1x.":".$pos1y.":".$pos1z.":".$level,
				"pos2" => $pos2x.":".$pos2y.":".$pos2z.":".$level,
				"牌子坐标" => $data["牌子坐标"],
				"白棋" => "35-0",
				"限制加入" => false,
				"黑棋" => "35-15",
				"棋盘" => "20-0",
				"胜利消息" => "§a恭喜你，以迅雷不及掩耳之势赢得了对战！",
				"失败消息" => "§a很遗憾，你输了哦~下次努力吧！",
				"tip提示" => "§6■当前玩家<{player}>    §e▲游戏时间<{time}秒>",
				"胜利指令" => array(),
				"失败指令" => array(),
				"空闲牌子" => array(
					"§e===W5-五子棋小游戏===",
					"§a点击加入游戏",
					"§7白方 {p1} | 黑方 {p2}",
					"§e=============="
				),
				"游戏牌子" => array(
					"§e===W5-五子棋小游戏===",
					"§c正在游戏中...",
					"§b{p1} §e对战 §6{p2}",
					"§e=============="
				)
			);
			$this->game->set($data["game"],$array);
			$this->game->save();
			$this->resetGame($data["game"]);
			return true;
			//$this->getServer()->getPlayerExact($playername)->sendMessage("§");
		}
		return false;
	}
	public function checkUpdate()//检查WTask更新
	{
		try
		{
			$info = json_decode(Utils::getURL("crazysnow.cc:8081/w5/update/update.json"), true);
			if(!isset($info["status"]) or $info["status"] !== true)
			{
				$this->getLogger()->warning("无法连接W更新服务器！");
				$this->activateUpdate=array("fail",0);
				return false;
			}
			if($info["latest"] != $this->currentVersion)
			{
				$this->getLogger()->notice(str_replace("%n","\n",$info["notice"]));
				$this->activateUpdate=array("yes",$info["latest"]);
				return $info["latest"];
			}
			else
			{
				$this->activateUpdate=array("no",0);
				$this->getServer()->getLogger()->info("暂无更新！");
				return "no";
			}
			return "success";
		}
		catch(\Throwable $e)
		{
			$this->getLogger()->logException($e);
			$this->activateUpdate=array("fail",0);
			$this->getLogger()->warning("无法连接W更新服务器！");
			return $false;
		}
	}
	public function getPharName()
	{
		$dir="plugins/";
		$dh = opendir($dir);
		while ($file=readdir($dh))
		{
			if($file!="." && $file!="..")
			{
				$fullpath = $dir."/".$file;
				if(!is_dir($fullpath))
				{
					$pharname=$file;
					$class=$this->getPluginLoader();
					if($class instanceof PharPluginLoader)
					{
						$class=$class->getPluginDescription($dir.$pharname);
						if($class->getName() == "W5")
						{
							return $pharname;
						}
					}
					else
					{
						return false;
					}
				}
				else
				{

				}
			}
		}
	}
}
