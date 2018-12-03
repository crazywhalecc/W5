<?php

namespace BlueWhale\W5\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use BlueWhale\W5\W5;
use BlueWhale\WTask\WTaskAPI;

class MainCommand extends Command
{
	private $plugin;
	private $checkTime=0;
	
	public function __construct(W5 $plugin)
	{
		$this->plugin=$plugin;
		$desc=$plugin->command->get("MainCommand");
		parent::__construct($desc["command"],$desc["description"]);
		$this->cmd=$desc["command"];$c=$this->cmd;
		$this->setPermission("w5.command.op");
		$this->setHelp=array(
			"§6=====W5设置棋盘菜单=====",
			"§a/".$c." set {name} addwincmd: §b添加胜利执行的指令(%p代玩家)",
			"§a/".$c." set {name} delwincmd: §b删除一个执行的胜利指令",
			"§a/".$c." set {name} addlosecmd: §b添加失败执行的指令(%p代玩家)",
			"§a/".$c." set {name} dellosecmd: §b删除一个执行的失败指令",
			"§a/".$c." set {name} sign: §b重新设置木牌位置"
		);
		$this->mainHelp=array(
			"§6=====W5-五子棋设置=====",
			"§7当前W5版本: ".$this->plugin->getDescription()->getVersion(),
			"§a/".$c." add <棋盘名称>: §b创建一个新的棋盘",
			"§a/".$c." set <棋盘名称>: §b设置棋盘",
			"§a/".$c." del <棋盘名称>: §b删除一个棋盘的设置",
			"§a/".$c." addwtask: §b添加一个对五子棋消费的WTask任务(需WTask已安装)",
			"§a/".$c." update: §b检查并升级W5五子棋插件",
			"§a/".$c." reload: §b重载所有数据"
		);
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
				case "reload":
					$this->plugin->game->reload();
					$this->plugin->config->reload();
					$this->plugin->command->reload();
					$this->plugin->pointconfig->reload();
					$this->plugin->user->reload();
					$this->plugin->resetGames();
					$sender->sendMessage("§a[W5] 成功重新加载所有数据！");
					return true;
				case "getphar":
					$sender->sendMessage(($this->plugin->getPharName() === false ? "错误的PHAR！" : $this->plugin->getPharName()));
					return true;
				case "addwtask":
					if($this->plugin->WTaskSetting === false)
					{
						$sender->sendMessage("§c你还没有安装WTask或WTask版本太旧，无法使用此功能！");
						return true;
					}
					if(isset($args[1]) && isset($args[2]))
					{
						$taskname=$args[1];
						$need=intval($args[2]);
						if(WTaskAPI::isTaskExists("normalTask",$taskname))
						{
							$att=$this->plugin->config->get("WTask专属任务");
							$att[$taskname]=$need;
							$this->plugin->config->set("WTask专属任务",$att);
							$this->plugin->config->save();
						}
						else
						{
							WTaskAPI::addNormalTask($taskname);
							$att=$this->plugin->config->get("WTask专属任务");
							$att[$taskname]=$need;
							$this->plugin->config->set("WTask专属任务",$att);
							$this->plugin->config->save();
						}
						$sender->sendMessage("§a[W5] 成功设置WTask专属任务！");
						return true;
					}
					else
					{
						$sender->sendMessage("§e[用法] /".$this->cmd." addwtask <任务名称> <运行任务需要积分>");
						return true;
					}
				case "update":
					if((time() - $this->checkTime) >= 60)
					{
						$this->checkTime=time();
						$this->plugin->checkUpdate();
						if($this->plugin->activateUpdate[0] == "no")
						{
							$sender->sendMessage("§e[W5] 你还没有检查更新，不能更新插件哦！");
							return true;
						}
						if(is_array($this->plugin->activateUpdate))
						{
							if($this->plugin->activateUpdate[0] == "yes")
							{
								file_put_contents("plugins/"."[7.0]W5_v".$this->plugin->activateUpdate[1] .".phar",file_get_contents("http://crazysnow.cc:8081/w5/update/[7.0]W5_v".$this->plugin->activateUpdate[1] .".phar"));
								$sender->sendMessage("§a[W5] 成功升级插件！请重启服务器！");
								@unlink("plugins/".$this->plugin->getPharName());
								return true;
							}
							elseif($this->plugin->activateUpdate[0] == "no")
							{
								$sender->sendMessage("§e[W5] 没有新版本.");
								return true;
							}
							else
							{
								$sender->sendMessage("§b[W5] 更新程序出错！可能是服务器未响应");
								return true;
							}
						}
						else
						{
							$sender->sendMessage("§b[W5] 更新程序出错！");
							return true;
						}
						return true;
					}
					else
					{
						$sender->sendMessage("歇一歇再检查吧！");
						$this->checkTime=time();
						return true;
					}
				case "add":
					if(isset($args[1]))
					{
						$gamename=$args[1];
						if($this->plugin->game->exists($gamename))
						{
							$sender->sendMessage("§e[W5] 对不起，这个名字的棋盘已经存在了！");
							return true;
						}
						$this->plugin->settingMode[$sender->getName()]=array("game" => $gamename,"step" => 0);
						$sender->sendMessage('§a[W5] 成功进入设置模式！请先点击一个方块，然后点击另一个方块设置棋盘范围！可输入 *cancel 取消！');
						return true;
					}
					else
					{
						$sender->sendMessage("§e[用法] /".$this->cmd." add <棋盘名称>");
						return true;
					}
				case "set":
					if(isset($args[1]))
					{
						$gamename=$args[1];
						if(!$this->plugin->game->exists($gamename))
						{
							$sender->sendMessage("§c[W5] 对不起，这个名字的棋盘不存在！");
							return true;
						}
						$data=$this->plugin->game->get($gamename);
						if(isset($args[2]))
						{
							switch($args[2])
							{
								case "addwincmd":
									if(isset($args[3]))
									{
										$i=3;
										while(isset($args[$i]))
										{
											$cmd[]=$args[$i];
											$i++;
										}
										$cmd=implode(" ",$cmd);
										$data["胜利指令"][]=$cmd;
										$this->plugin->game->set($gamename,$data);
										$this->plugin->game->save();
										$sender->sendMessage("§a[W5] 成功添加一个指令！");
										return true;
									}
									else
									{
										$sender->sendMessage("§e[用法] /".$this->cmd." set ".$gamename." addwincmd <指令>");
										return true;
									}
								case "delwincmd":
									if(isset($args[3]))
									{
										$i=3;
										while(isset($args[$i]))
										{
											$cmd[]=$args[$i];
											$i++;
										}
										$cmd=implode(" ",$cmd);
										$cmds=$data["胜利指令"];
										if(in_array($cmd,$cmds))
										{
											$inv=array_search($cmd,$cmds);
											array_splice($cmds,$inv,1);
											$data["胜利指令"]=$cmds;
											$this->plugin->game->set($gamename,$data);
											$this->plugin->game->save();
										}
										$sender->sendMessage("§a[W5] 成功删除指令！");
										return true;
									}
									else
									{
										$sender->sendMessage("§e[用法] /".$this->cmd." set ".$gamename." delwincmd <指令>");
										return true;
									}
								case "addlosecmd":
									if(isset($args[3]))
									{
										$i=3;
										while(isset($args[$i]))
										{
											$cmd[]=$args[$i];
											$i++;
										}
										$cmd=implode(" ",$cmd);
										$data["失败指令"][]=$cmd;
										$this->plugin->game->set($gamename,$data);
										$this->plugin->game->save();
										$sender->sendMessage("§a[W5] 成功添加一个指令！");
										return true;
									}
									else
									{
										$sender->sendMessage("§e[用法] /".$this->cmd." set ".$gamename." addlosecmd <指令>");
										return true;
									}
								case "dellosecmd":
									if(isset($args[3]))
									{
										$i=3;
										while(isset($args[$i]))
										{
											$cmd[]=$args[$i];
											$i++;
										}
										$cmd=implode(" ",$cmd);
										$cmds=$data["失败指令"];
										if(in_array($cmd,$cmds))
										{
											$inv=array_search($cmd,$cmds);
											array_splice($cmds,$inv,1);
											$data["失败指令"]=$cmds;
											$this->plugin->game->set($gamename,$data);
											$this->plugin->game->save();
										}
										$sender->sendMessage("§a[W5] 成功删除指令！");
										return true;
									}
									else
									{
										$sender->sendMessage("§e[用法] /".$this->cmd." set ".$gamename." dellosecmd <指令>");
										return true;
									}
								case "sign":
									$this->plugin->settingMode[$sender->getName()]["gamename"]=$gamename;
									$this->plugin->settingMode[$sender->getName()]["step"]=3;
									$sender->sendMessage("§a请点击一个木牌！~");
									return true;
							}
						}
						else
						{
							$help=implode("%n",$this->setHelp);
							$sender->sendMessage(str_replace(["%n","{name}"],["\n",$gamename],$help));
							return true;
						}
					}
					else
					{
						$sender->sendMessage("§b[用法] /".$this->cmd." set <棋盘名称>");
						return true;
					}
				case "del":
					if(isset($args[1]))
					{
						$gamename=$args[1];
						if(!$this->plugin->game->exists($gamename))
						{
							$sender->sendMessage("§c[W5] 对不起，这个名字的棋盘不存在！");
							return true;
						}
						$this->plugin->game->remove($gamename);
						$this->plugin->game->save();
						$sender->sendMessage("§a[W5] 成功删除棋盘！由于方块问题，棋盘方块需要手动清理！");
						return true;
					}
					else
					{
						$sender->sendMessage("§e[用法] /".$this->cmd." del <棋盘名称>");
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