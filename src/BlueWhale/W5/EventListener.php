<?php

namespace BlueWhale\W5;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\BlockBreakEvent;
use BlueWhale\W5\scheduleTasks\CallbackTask;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

class EventListener implements Listener
{
	private $plugin;
	
	public function __construct(W5 $plugin)
	{
		$this->plugin=$plugin;
		$plugin->getServer()->getPluginManager()->registerEvents($this,$plugin);
	}
	public function onMsg(PlayerCommandPreprocessEvent $event)
	{
		$msg=$event->getMessage();
		$player=$event->getPlayer();
		if($msg == "*cancel")
		{
			if(isset($this->plugin->settingMode[$player->getName()]))
			{
				unset($this->plugin->settingMode[$player->getName()]);
				$event->setCancelled();
				$player->sendMessage("成功取消设置！");
			}
		}
	}
	public function onQuit(PlayerQuitEvent $event)
	{
		$name=$event->getPlayer()->getName();
		foreach($this->plugin->gameStatus as $gamename=>$temp)
		{
			if($temp["p1"] == $name || $temp["p2"] == $name)
			{
				if($this->plugin->gameStatus[$gamename]["status"] === true)
				{
					$time=$this->plugin->config->get("掉线超时")*20;
					if($temp["p1"] == $name)
					{
						$this->plugin->getServer()->getPlayerExact($temp["p2"])->sendMessage("§e对方已退出服务器，正在等待重新加入...");
						$this->plugin->waitRecover[$name]=[$gamename,1];
						$this->plugin->rejoinTick[$name]=$this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this->plugin,"waitRejoin"],[$gamename,$name,1]),$time);
					}
					else
					{
						$this->plugin->getServer()->getPlayerExact($temp["p1"])->sendMessage("§e对方已退出服务器，正在等待重新加入...");
						$this->plugin->waitRecover[$name]=[$gamename,1];
						$this->plugin->rejoinTick[$name]=$this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this->plugin,"waitRejoin"],[$gamename,$name,2]),$time);
					}
					//$this->plugin->forceStopGame($gamename,"玩家离开服务器，游戏结束！");
				}
				else
					$this->plugin->resetGame($gamename);
				return;
			}
		}
	}
	public function onJoin(PlayerJoinEvent $event)
	{
		$name=$event->getPlayer()->getName();
		$p=$event->getPlayer();
		if(isset($this->plugin->waitRecover[$name]))
		{
			$this->plugin->rejoinTick[$name]->remove();
			$event->sendMessage("§a[W5] 欢迎回来！正在传送到游戏棋牌中，请稍后~");
			$this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"delayedTeleport"],[$p,$this->plugin->waitRecover[$name][0]]),40);
			unset($this->plugin->waitRecover[$name]);
		}
	}
	public function delayedTeleport($p,$gamename)
	{
		$p->teleport($this->plugin->getGamePos($gamename));
	}
	public function onBreak(BlockBreakEvent $event)
	{
		$block=$event->getBlock();
		foreach($this->plugin->game->getAll() as $gamename=>$data)
		{
			if($this->plugin->isInPlate($gamename,$block) === true)
				$event->setCancelled();
			elseif($this->plugin->isInPlate($gamename,$block) == "up")
				$event->setCancelled();
		}
	}
	public function onTeleport(EntityTeleportEvent $event)
	{
		$name=$event->getEntity();
		if($name instanceof Player)
		foreach($this->plugin->gameStatus as $gamename=>$temp)
		{
			if($temp["p1"] == $name || $temp["p2"] == $name)
			{
				if($this->plugin->gameStatus[$gamename]["status"] === true)
					$this->plugin->forceStopGame($gamename,"玩家离开游戏棋盘，游戏结束！");
				else
					$this->plugin->resetGame($gamename);
				return;
			}
		}
	}
	public function onTouch(PlayerInteractEvent $event)
	{
		$player=$event->getPlayer();
		$name=$player->getName();
		$block=$event->getBlock();
		if(isset($this->plugin->settingMode[$name]))
		{
			$setdata=$this->plugin->settingMode[$name];
			switch($setdata["step"])
			{
				case 0:
					$block=$event->getBlock();
					$x=$block->x;$y=$block->y;$z=$block->z;$level=$block->level->getFolderName();
					$pos1=array($x,$y,$z,$level);
					$this->plugin->settingMode[$name]["pos1"]=$pos1;
					$this->plugin->settingMode[$name]["step"]=1;
					$player->sendMessage("§a[W5] 成功设置第一个点！接下来请设置第二个点！");
					//$event->setCancelled();
					
					break;
					return;
				case 1:
					$block=$event->getBlock();
					$x=$block->x;$y=$block->y;$z=$block->z;$level=$block->level->getFolderName();
					if($this->plugin->settingMode[$name]["pos1"][1]!=$y)
					{
						$player->sendMessage("§c[W5] 设置错误！请选择一个在同一平面的方块来设置大小！");
						//$event->setCancelled();
						break;
						return;
					}
					$pos1=$this->plugin->settingMode[$name]["pos1"];
					$dx=($pos1[0] > $x) ? ($pos1[0] - $x) : ($x - $pos1[0]);
					$dz=($pos1[2] > $z) ? ($pos1[2] - $z) : ($z - $pos1[2]);
					if($dx < 10 or $dz < 10)
					{
						$player->sendMessage("§e[W5] 。。。你的棋盘敢再小点么");
						//$event->setCancelled();
						break;
						return;
					}
					$pos2=array($x,$y,$z,$level);
					$this->plugin->settingMode[$name]["pos2"]=$pos2;
					$this->plugin->settingMode[$name]["step"]=2;
					$player->sendMessage("§a[W5] 成功设置区域，请点击一个开始游戏的木牌！");
					$event->setCancelled();
					unset($x,$y,$z,$level);
					break;
					return;
				case 2:
					$block=$event->getBlock();
					if($block->getId() == 63 || $block->getId() == 68 || $block->getId() == 323)
					{
						$signPos=$block->x.":".$block->y.":".$block->z.":".$block->level->getFolderName();
						$this->plugin->settingMode[$name]["牌子坐标"]=$signPos;
						$this->plugin->createGame($name);
						$player->sendMessage("§a[W5] 成功创建游戏！");
						unset($this->plugin->settingMode[$name]);
						//$event->setCancelled();
						return;
					}
					else
					{
						return;
					}
				case 3:
					$block=$event->getBlock();
					if($block->getId() == 63 || $block->getId() == 68 || $block->getId() == 323)
					{
						$signPos=$block->x.":".$block->y.":".$block->z.":".$block->level->getFolderName();
						$data=$this->plugin->game->get($this->plugin->settingMode[$name]["gamename"]);
						$data["牌子坐标"]=$signPos;
						$this->plugin->game->set($this->plugin->settingMode[$name]["gamename"],$data);
						$this->plugin->game->save();
						$player->sendMessage("§a[W5] 成功设置游戏木牌！");
						unset($this->plugin->settingMode[$name]);
						//$event->setCancelled();
						return;
					}
					else
					{
						return;
					}
			}
		}
		
	}
}