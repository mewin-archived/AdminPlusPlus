<?php
namespace mewin;

use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;

class AdminPlugPlus implements Plugin, CommandListener, CallbackListener
{
	const ID = 85;
	const VERSION = "1.0";
	const TABLE_APP_NICKS = "mc_adm_nicks";
	const SETTINGS_PERMISSION_INFO = "View extended player information";
	
	private $maniaControl, $mysqli;
    
    public function load(ManiaControl $maniaControl)
    {
        $this->maniaControl = $maniaControl;
        $this->mysqli = $maniaControl->database->getMysqli();
        $this->initSettings();
        $this->initTables();
        $this->registerListeners();
    }
    
    private function registerListeners()
    {
        $this->maniaControl->callbackManager->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, "handlePlayerJoin");
        $this->maniaControl->commandManager->registerCommandListener('pinfo', $this, 'command_playerinfo', true, 'Information about a player');
        $this->maniaControl->commandManager->registerCommandListener('nicks', $this, 'command_nicks', true, 'View a players known nicknames');
    }
    
    private function initSettings()
    {
        $this->maniaControl->authenticationManager->definePermissionLevel(self::SETTINGS_PERMISSION_INFO, AuthenticationManager::AUTH_LEVEL_MODERATOR);
    }
	
	private function initTables()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_APP_NICKS . "` (
                `index` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, 
                `playerIndex` int(11) NOT NULL,
                `nick` VARCHAR(150) NOT NULL,
                PRIMARY KEY (`index`),
				UNIQUE(`playerIndex`, `nick`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Chat Log' AUTO_INCREMENT=1;";
        $st = $this->mysqli->prepare($sql);
        if ($this->mysqli->error)
        {
            trigger_error($this->mysqli->error, E_USER_ERROR);
            return false;
        }
        $st->execute();
        if ($st->error)
        {
            trigger_error($st->error, E_USER_ERROR);
            return false;
        }
        $st->close();
        
        return true;
    }

    public function unload()
    {
        $this->maniaControl = null;
    }

    public static function prepare(ManiaControl $maniaControl)
    {
        
    }

    public static function getAuthor()
    {
        return "mewin";
    }

    public static function getDescription()
    {
        return "Advanced admin tools";
    }

    public static function getId()
    {
        return self::ID;
    }

    public static function getName()
    {
        return "Admin++";
    }

    public static function getVersion()
    {
        return self::VERSION;
    }
	
	private function addNick($player)
	{
		$playerIndex = $player->index;
		$nick = $player->nickname;
		$sql = "INSERT INTO `" . self::TABLE_APP_NICKS . "`(
				`playerIndex`, `nick`
				) VALUES (
				?, ?
				)
				ON DUPLICATE KEY UPDATE `playerIndex` = `playerIndex`;";
        $st = $this->mysqli->prepare($sql);
        if ($this->mysqli->error)
        {
            trigger_error($this->mysqli->error);
            return false;
        }
        $st->bind_param('is', $playerIndex, $nick);
        $st->execute();
        if ($st->error)
        {
            trigger_error($st->error);
            return false;
        }
        $st->close();
		return true;
	}
    
    public function handlePlayerJoin(Player $player)
    {
		return $this->addNick($player);
    }
	
	private function getNicks(Player $player)
	{
		$playerIndex = $player->index;
		$sql = "SELECT `nick` 
				FROM `" . self::TABLE_APP_NICKS . "`
				WHERE `playerIndex` = ?
				ORDER BY `index`";
		$st = $this->mysqli->prepare($sql);
		if ($this->mysqli->error)
		{
            trigger_error($this->mysqli->error);
            return false;
		}
		$st->bind_param('i', $playerIndex);
		$st->execute();
		if ($st->error)
		{
			$st->close();
			trigger_error($st->error);
			return false;
		}
		
		$nicks = array();
		$nick = "";
		
		$st->bind_result($nick);
		while ($st->fetch())
		{
			$nicks[] = $nick;
		}
		
		$st->free_result();
		$st->close();
		
		if (empty($nicks))
		{ // didnt add a nick yet? do it now!
			$this->addNick($player);
			$nicks[] = $player->nickname;
		}
		
		return $nicks;
	}
    
    public function command_playerinfo(array $chatCallback, Player $player)
    {
        if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTINGS_PERMISSION_INFO))
        {
            $this->maniaControl->authenticationManager->sendNotAllowed($player);
            return;
        }
        
        $cmd = explode(' ', $chatCallback[1][2]);
        if (empty($cmd[1]))
        {
            $this->maniaControl->chat->sendUsageInfo("Usage example: '//pinfo login'", $player->login);
        }
        else
        {
            $target = $this->maniaControl->playerManager->getPlayer($cmd[1]);
            if (!$target)
            {
                $this->maniaControl->chat->sendError("Player '{$cmd[1]}' not found!", $player->login);
                return;
            }
			
			// todo
        }
    }
    
    public function command_nicks(array $chatCallback, Player $player)
    {
        if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTINGS_PERMISSION_INFO))
        {
            $this->maniaControl->authenticationManager->sendNotAllowed($player);
            return;
        }
        
        $cmd = explode(' ', $chatCallback[1][2]);
        if (empty($cmd[1]))
        {
            $this->maniaControl->chat->sendUsageInfo("Usage example: '//nicks login'", $player->login);
        }
        else
        {
            $target = $this->maniaControl->playerManager->getPlayer($cmd[1]);
            if (!$target)
            {
                $this->maniaControl->chat->sendError("Player '{$cmd[1]}' not found!", $player->login);
                return;
            }
			
			$nicks = $this->getNicks($target);
			$text = implode('$z, ', $nicks);
			$this->maniaControl->chat->sendInformation("Known nicks of \"{$cmd[1]}\": {$text}", $player->login);
        }
    }
}
?>