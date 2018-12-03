<?php

namespace BlueWhale\W5\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use BlueWhale\W5\W5;
use pocketmine\Player;
use BlueWhale\WTask\WTaskAPI;

class UserCommand extends Command
{
	private $plugin;
	
	public function __construct(W5 $plugin)
	{
		$this->plugin=$plugin;
		$desc=$plugin->command->get("UserCommand");
		parent::__construct($desc["command"],$desc["description"]);
		$this->cmd=$desc["command"];
		$c=$this->cmd;
		$this->setPermission("w5.command.player");
		$this->mainHelp=array(
			"§6=====五子棋=====",
			"§a/".$c." 我: §b查看我的战绩",
			"§a/".$c." see: §b查看其他玩家的战绩",
			"§a/".$c." 积分排行: §b查看积分排行",
			"§a/".$c." 战胜排行: §b查看赢次排行",
			"§a/".$c." 消费积分: §c消费积分",
			"§a/".$c." 加入: §b加入一个五子棋游戏",
			"§a/".$c." tp: §b传送到目标棋盘并加入游戏"
		);
	}
	public function showTopList($p)
	{
		$p->sendMessage("§6=====五子棋积分排行榜=====");
		$list=$this->plugin->getAllPoint();
		$i=1;
		foreach($list as $pname=>$dat)
		{
			$p->sendMessage($this->plugin->pointconfig->get("排行榜颜色")."[".$i."] ".$pname.": ".$dat);
			$i++;
			if($i == 8)
				break;
		}
	}
	public function showTopWinList($p)
	{
		$p->sendMessage("§6=====五子棋胜利排行榜=====");
		$list=$this->plugin->getAllWinTime();
		$i=1;
		foreach($list as $pname=>$dat)
		{
			$p->sendMessage($this->plugin->pointconfig->get("排行榜颜色")."[".$i."] ".$pname.": ".$dat);
			$i++;
			if($i == 8)
				break;
		}
	}
	public function execute(CommandSender $sender,$label,array $args)
	{
		if($this->plugin->isEnabled() == false)
			return false;
		if(!$this->testPermission($sender))
			return false;
		if(isset($args[0]))
		{
			switch($args[0])
			{
				case "积分排行":
					$this->showTopList($sender);
					return true;
				case "战胜排行":
					$this->showTopWinList($sender);
					return true;
				case "我":
					if(!$sender instanceof Player)
					{
						$sender->sendMessage("请在游戏内输入指令~！");
						return true;
					}
					$sender->sendMessage("§6=====五子棋-个人战绩=====");
					if($this->plugin->getPoint(strtolower($sender->getName())) === null)
					{
						$sender->sendMessage("§e你还没有玩过五子棋哦, 暂无数据！");
						return true;
					}
					$data=$this->plugin->getData($sender);
					$total=$data["win"]+$data["lose"]+$data["peace"];
					$winpercentage=($data["win"] == 0 ? 0 : ($total != 0 ? ($data["win"]/$total) : 0))*100;
					$sender->sendMessage("§a[玩家] §b".$sender->getName());
					$sender->sendMessage("§6[积分] §b".$data["point"]);
					$sender->sendMessage("§6[排名] §b".$this->plugin->getMyGrade($sender));
					$sender->sendMessage("§e[胜次] §b".$data["win"]);
					$sender->sendMessage("§e[败次] §b".$data["lose"]);
					$sender->sendMessage("§e[胜率] §b".$winpercentage.'%');
					return true;
				case "see":
					if(!isset($args[1]))
					{
						$sender->sendMessage("[用法] /".$this->cmd." see <玩家ID>");
						return true;
					}
					$name=$args[1];
					$sender->sendMessage("§6=====五子棋-个人战绩=====");
					if($this->plugin->getPoint($name) === null)
					{
						$sender->sendMessage("§eta还没有玩过五子棋哦, 暂无数据！");
						return true;
					}
					$data=$this->plugin->getData($name);
					$total=$data["win"]+$data["lose"]+$data["peace"];
					$winpercentage=($data["win"] == 0 ? 0 : ($total != 0 ? ($data["win"]/$total) : 0))*100;
					$winpercentage = number_format($winpercentage, 2, '.', '');
					$sender->sendMessage("§a[玩家] §b".$name);
					$sender->sendMessage("§6[积分] §b".$data["point"]);
					$sender->sendMessage("§6[排名] §b".$this->plugin->getMyGrade($name));
					$sender->sendMessage("§e[胜次] §b".$data["win"]);
					$sender->sendMessage("§e[败次] §b".$data["lose"]);
					$sender->sendMessage("§e[胜率] §b".$winpercentage.'%');
					return true;
				case "消费积分":
					if($this->plugin->WTaskSetting === false)
					{
						$sender->sendMessage("§c服务器还没有安装WTask或WTask版本太旧，无法使用消费功能！");
						return true;
					}
					if(isset($args[1]))
					{
						$taskname=$args[1];
						if(isset($this->plugin->config->get("WTask专属任务")[$taskname]))
						{
							$need=$this->plugin->config->get("WTask专属任务")[$taskname];
							if($this->plugin->getPoint($sender) < $need)
							{
								$sender->sendMessage("§c你的积分不够哦！");
								return true;
							}
							$this->plugin->reducePoint($sender,$need);
							if(WTaskAPI::isTaskExists("normalTask",$taskname))
								WTaskAPI::preNormalTask($taskname,$sender);
							else
							{
								$sender->sendMessage("WTask任务不存在！");
								return true;
							}
							return true;
						}
					}
					else
					{
						$sender->sendMessage("§e[用法] /".$this->cmd." 消费积分 <消费项目名称>");
						return true;
					}
				case "加入":
				case "tp":
					if(isset($args[1]))
					{
						$gamename=$args[1];
						$game=$gamename;
						$name=$sender->getName();
						if($this->plugin->game->exists($gamename))
						{
							if($this->plugin->gameStatus[$gamename]["status"] === false)
							{
								if($this->plugin->gameStatus[$game]["p1"] == "")
								{
									$this->plugin->joinGame($game,$name,1);
									$sender->teleport($this->plugin->getGamePos($gamename));
								}
								elseif($this->plugin->gameStatus[$game]["p2"] == "")
								{
									$this->plugin->joinGame($game,$name,2);
									$sender->teleport($this->plugin->getGamePos($gamename));
								}
								else
								{
									$sender->sendMessage("§e[W5] 对不起，已经有人在开始游戏了！");
								}
								return true;
							}
							else
							{
								$sender->sendMessage("§e[W5] 对不起，已经有人在开始游戏了！");
								return true;
							}
						}
						else
						{
							$sender->sendMessage("§e[W5] 对不起，棋盘 $gamename 不存在！");
							return true;
						}
					}
					else
					{
						$sender->sendMessage("§e[用法] /".$this->cmd." [加入/tp] <棋盘名称>");
						return true;
					}
				default:
					$help=implode("%n",$this->mainHelp);
					$sender->sendMessage(str_replace("%n","\n",$help));
					return true;
			}
		}
		else
		{
			$help=implode("%n",$this->mainHelp);
			$sender->sendMessage(str_replace("%n","\n",$help));
			return true;
		}
	}
}