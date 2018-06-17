<?php

declare(strict_types=1);
namespace muqsit\worldstyler;

use muqsit\worldstyler\schematics\Schematic;
use muqsit\worldstyler\shapes\CommonShape;
use muqsit\worldstyler\shapes\Cuboid;
use muqsit\worldstyler\utils\Utils;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;

class WorldStyler extends PluginBase {

    /** @var PlayerSelection[] */
    private $selections = [];

    public function onEnable() : void
    {
        $this->getServer()->getPluginManager()->registerEvents(new EventHandler($this), $this);

        if (!is_dir($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }

        if (!is_dir($this->getDataFolder() . 'schematics/')) {
            mkdir($this->getDataFolder() . 'schematics/');
        }

        $this->saveResource("config.yml");
    }

    public function getPlayerSelection(Player $player) : ?Selection
    {
        return $this->getSelection($player->getId());
    }

    public function getSelection(int $pid) : ?Selection
    {
        return $this->selections[$pid] ?? ($this->selections[$pid] = new Selection($pid));
    }

    public function removeSelection(int $pid) : void
    {
        unset($this->selections[$pid]);
    }

    public function onCommand(CommandSender $issuer, Command $cmd, $label, array $args) : bool
    {
        $cmd = $cmd->getName();
        switch ($cmd) {
            case '/pos1':
                $this->getPlayerSelection($issuer)->setPosition(1, $issuer->asVector3());
                $issuer->sendMessage(TF::GREEN . 'Selected position #1 as X=' . $issuer->x . ', Y=' . $issuer->y . ', Z=' . $issuer->z);
                return true;
            case '/pos2':
                $this->getPlayerSelection($issuer)->setPosition(2, $issuer->asVector3());
                $issuer->sendMessage(TF::GREEN . 'Selected position #2 as X=' . $issuer->x . ', Y=' . $issuer->y . ', Z=' . $issuer->z);
                return true;
            case '/copy':
                $selection = $this->getPlayerSelection($issuer);
                $count = $selection->getPositionCount();

                if ($count < 2) {
                    $issuer->sendMessage(TF::RED . 'You have not selected enough vertices.');
                    return false;
                }

                $cuboid = Cuboid::fromSelection($selection);
                if ($this->getConfig()->get("use-async-tasks", false)) {
                    $cuboid = $cuboid->async();
                }

                $cuboid->copy(
                    $issuer->getLevel(),
                    $issuer->asVector3(),
                    function (float $time, int $changed) use ($issuer) : void {
                        $issuer->sendMessage(TF::GREEN . 'Copied ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's into your clipboard.');
                    }
                );
                return true;
            case '/paste':
                $selection = $this->getPlayerSelection($issuer);

                if (!$selection->hasClipboard()) {
                    $issuer->sendMessage(TF::RED . 'You have copied nothing into your clipboard.');
                    return false;
                }

                $air = !(isset($args[0]) && $args[0] === "noair");

                $common_shape = CommonShape::fromSelection($selection);
                if ($this->getConfig()->get("use-async-tasks", false)) {
                    $cuboid = $common_shape->async();
                }

                $common_shape->paste(
                    $issuer->getLevel(),
                    $issuer->asVector3(),
                    $air,
                    function (float $time, int $changed) use ($issuer, $air) : void {
                        $issuer->sendMessage(TF::GREEN . 'Pasted ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's from your clipboard' . ($air ? null : ' (no-air)') . '.');
                    }
                );
                return true;
            case '/stack':
                $selection = $this->getPlayerSelection($issuer);

                if (!$selection->hasClipboard()) {
                    $issuer->sendMessage(TF::RED . 'You have copied nothing into your clipboard.');
                    return false;
                }

                if (!isset($args[0]) || !is_numeric($args[0]) || strpos($args[0], '.') !== false || $args[0] <= 0) {
                    $issuer->sendMessage(TF::RED . '//stack <repititions>');
                    return false;
                }

                $air = !(isset($args[1]) && $args[1] === "noair");

                $common_shape = CommonShape::fromSelection($selection);
                if ($this->getConfig()->get("use-async-tasks", false)) {
                    $cuboid = $common_shape->async();
                }

                $increase = $issuer->getDirectionVector()->round();
                $repititions = (int) $args[0];

                $issuer->sendMessage(TF::YELLOW . 'Stacking (Multiplying by ' . $increase->__toString() . ')...');

                $common_shape->stack(
                    $issuer->getLevel(),
                    $issuer->asVector3(),
                    $increase,
                    $repititions,
                    $air,
                    function (float $time, int $changed) use ($issuer, $air) : void {
                        $issuer->sendMessage(TF::GREEN . 'Stacked ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's from your clipboard' . ($air ? null : ' (no-air)') . '.');
                    }
                );
                return true;
            case '/set':
                $selection = $this->getPlayerSelection($issuer);
                $count = $selection->getPositionCount();

                if ($count < 2) {
                    $issuer->sendMessage(TF::RED . 'You have not selected enough vertices.');
                    return false;
                }

                if (!isset($args[0])) {
                    $issuer->sendMessage(TF::RED . '//set <block>');
                    return false;
                }

                $block = Utils::getBlockFromString($args[0]);
                if ($block === null) {
                    $issuer->sendMessage(TF::RED . 'Invalid block given.');
                    return false;
                }

                $cuboid = Cuboid::fromSelection($selection);
                if ($this->getConfig()->get("use-async-tasks", false)) {
                    $cuboid = $cuboid->async();
                }

                $cuboid->set(
                    $issuer->getLevel(),
                    $block,
                    function (float $time, int $changed) use ($issuer) : void {
                        $issuer->sendMessage(TF::GREEN . 'Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
                    }
                );
                return true;
            case '/replace':
                $selection = $this->getPlayerSelection($issuer);
                $count = $selection->getPositionCount();
                if ($count < 2) {
                    $issuer->sendMessage(TF::RED . 'You have not selected enough vertices.');
                    return false;
                }
                if (!isset($args[1])) {
                    $issuer->sendMessage(TF::RED . '//replace <blockToReplace> <replacementBlock>');
                    return false;
                }

                [$block1, $block2] = $args;

                $block1 = Utils::getBlockFromString($block1);
                if ($block1 === null) {
                    $issuer->sendMessage(TF::RED . 'Invalid block ' . $block1 . ' given.');
                    return false;
                }

                $block2 = Utils::getBlockFromString($block2);
                if ($block2 === null) {
                    $issuer->sendMessage(TF::RED . 'Invalid block ' . $block2 . ' given.');
                    return false;
                }

                $cuboid = Cuboid::fromSelection($selection);
                if ($this->getConfig()->get("use-async-tasks", false)) {
                    $cuboid = $cuboid->async();
                }

                $cuboid->replace(
                    $issuer->getLevel(),
                    $block1,
                    $block2,
                    function (float $time, int $changed) use ($issuer) : void {
                        $issuer->sendMessage(TF::GREEN . 'Replaced ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
                    }
                );
                return true;
            case '/schem':
                if (!isset($args[0]) || ($args[0] !== 'list' && $args[0] !== 'paste') || ($args[0] === 'paste' && !isset($args[1]))) {
                    $issuer->sendMessage(TF::RED . '//schem list');
                    $issuer->sendMessage(TF::RED . '//schem paste <schematicname>');
                    return false;
                }

                if ($args[0] === 'list') {
                    $dir = $this->getDataFolder() . 'schematics/';
                    if (!is_dir($dir)) {
                        $issuer->sendMessage(TF::RED . 'Directory ' . $dir . ' does not exist.');
                        return false;
                    }

                    $files = 0;
                    $issuer->sendMessage(TF::YELLOW . 'Schematics:');
                    foreach (scandir($dir) as $file) {
                        $expl = explode(".", $file, 2);
                        if (count($expl) === 2 && $expl[1] === 'schematic') {
                            $issuer->sendMessage(TF::GREEN . ++$files . '. ' . $expl[0] . TF::GRAY . ' (' . Utils::humanFilesize($dir . $file) . ')');
                        }
                    }
                    $issuer->sendMessage(TF::ITALIC . TF::GRAY . 'Found ' . $files . ' schematics!');
                    return true;
                }

                if ($args[0] === 'paste') {
                    $file = $this->getDataFolder() . 'schematics/' . $args[1] . '.schematic';
                    if (!is_file($file)) {
                        $issuer->sendMessage(TF::RED . 'File "' . $file . '" not found.');
                        return false;
                    }

                    $schematic = new Schematic($file);
                    $is_async = $this->getConfig()->get("use-async-tasks", false);

                    if ($is_async) {
                        $schematic = $schematic->async();
                    } else {
                        $schematic->load();
                    }

                    $schematic->paste(
                        $issuer->getLevel(),
                        $issuer->asVector3(),
                        true,
                        function (float $time, int $changed) use ($issuer) : void {
                            $issuer->sendMessage(TF::GREEN . 'Took ' . number_format($time, 10) . 's to paste ' . number_format($changed) . ' blocks.');
                        }
                    );

                    if (!$is_async) {
                        $schematic->invalidate();
                    }
                }
                return true;
        }
        $issuer->sendMessage(TF::RED . 'Invalid syntax.');
        return false;
    }
}
