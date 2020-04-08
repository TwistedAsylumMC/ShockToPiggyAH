<?php
declare(strict_types=1);

namespace twisted\shocktopiggyah;

use DaPigGuy\PiggyAuctions\PiggyAuctions;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use SQLite3;
use SQLiteException;
use function count;
use function file_exists;
use function hex2bin;
use function substr;
use function time;
use const SQLITE3_OPEN_READONLY;

class ShockToPiggyAH extends PluginBase{

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(count($args) < 1){
			$sender->sendMessage(TextFormat::RED . "Use '/convertah <database name>'");

			return true;
		}

		$file = $args[0];
		if(!file_exists($this->getDataFolder() . $file)){
			$sender->sendMessage(TextFormat::RED . $file . " does not exist in /plugin_data/" . $this->getDescription()->getName());

			return true;
		}
		if(substr($file, -3) !== ".db" && substr($file, -7) !== ".sqlite"){
			$sender->sendMessage(TextFormat::RED . $file . " is not a valid database");

			return true;
		}

		$sender->sendMessage(TextFormat::GREEN . "Database conversion started");

		$piggyAuctionManager = PiggyAuctions::getInstance()->getAuctionManager();
		$converted = 0;
		try{
			$sqlite = new SQLite3($this->getDataFolder() . $file, SQLITE3_OPEN_READONLY);
			$result = $sqlite->query("SELECT username, price, nbt, end_time, expired FROM auctions");
			while($row = $result->fetchArray()){
				if(!(bool) $row["expired"]){
					$nbt = (new BigEndianNBTStream())->readCompressed(hex2bin($row["nbt"]));
					if($nbt instanceof CompoundTag){
						$item = Item::nbtDeserialize($nbt);
						$piggyAuctionManager->addAuction($row["username"], $item, time(), (int) $row["end_time"], (int) $row["price"]);

						++$converted;
					}
				}
			}
		}catch(SQLiteException $exception){
			$converted = -1;

			$sender->sendMessage(TextFormat::RED . "Error when converting database: " . $exception->getMessage());
			$this->getLogger()->logException($exception);
		}

		if($converted >= 0){
			$sender->sendMessage(TextFormat::GREEN . "Successfully converted " . $converted . " auctions to PiggyAuctions");
		}

		return true;
	}
}