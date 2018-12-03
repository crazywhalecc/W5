<?php

namespace BlueWhale\W5;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

class Game extends W5 implements Listener
{
	public $doubleTap = [];
	
	public function __construct($gamename,$plugin)
	{
		$game=$plugin->game->get($gamename);
		$this->name=$gamename;
		$this->pos1=$game["pos1"];
		$this->pos2=$game["pos2"];
		$this->sign=$game["牌子坐标"];
		$this->plugin=$plugin;
		$this->plugin->getServer()->getPluginManager()->registerEvents($this,$plugin);
	}
	public function reloadGame()
	{
		
	}
	public function onTap(PlayerInteractEvent $event)
	{
		$game=$this->name;
		$block=$event->getBlock();
		$player=$event->getPlayer();
		$name=$player->getName();
		$pos=$block->x.":".$block->y.":".$block->z.":".$block->level->getFolderName();
		if($pos == $this->sign)
		{
			if($this->plugin->gameStatus[$game]["p1"] == "")
			{
				$this->plugin->joinGame($game,$name,1);
			}
			elseif($this->plugin->gameStatus[$game]["p2"] == "")
			{
				$this->plugin->joinGame($game,$name,2);
			}
			elseif($this->plugin->gameStatus[$game]["status"] === true)
			{
				$player->sendMessage("§e[W5] 对不起，已经有人在开始游戏了！");
				//$event->setCancelled();
			}
			return;
		}
		elseif($this->plugin->isInPlate($this->name,$block))
		{
			if($name != $this->plugin->gameStatus[$game]["p1"] && $name != $this->plugin->gameStatus[$game]["p2"])
			{
				return;
				//$event->setCancelled();
			}
			else
			{
				$data=$this->plugin->game->get($game);
				$team1block=$data["白棋"];
				$team2block=$data["黑棋"];
				$baseblock=$data["棋盘"];
				if($this->plugin->gameStatus[$game]["status"] === true)
				{
					if($block->getId()."-".$block->getDamage() == $team1block || $block->getId()."-".$block->getDamage() == $team2block)
					{
						//$event->setCancelled();
					}
					elseif($block->getId()."-".$block->getDamage() == $baseblock)
					{
						$team=$this->plugin->gameStatus[$game]["p1"] == $name ? 1 : ($this->plugin->gameStatus[$game]["p2"] == $name ? 2 : 2);
						if($team == $this->plugin->gameStatus[$game]["should"])
							$this->plugin->updateBlock($game,$block,$team);
						//$event->setCancelled();
					}
				}
			}
		}
	}
}