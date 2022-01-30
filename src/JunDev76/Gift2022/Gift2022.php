<?php

/*
       _             _____           ______ __
      | |           |  __ \         |____  / /
      | |_   _ _ __ | |  | | _____   __ / / /_
  _   | | | | | '_ \| |  | |/ _ \ \ / // / '_ \
 | |__| | |_| | | | | |__| |  __/\ V // /| (_) |
  \____/ \__,_|_| |_|_____/ \___| \_//_/  \___/


This program was produced by JunDev76 and cannot be reproduced, distributed or used without permission.

Developers:
 - JunDev76 (https://github.jundev.me/)

Copyright 2022. JunDev76. Allrights reserved.
*/

namespace JunDev76\Gift2022;

use Exception;
use FormSystem\form\ButtonForm;
use JsonException;
use JunKR\communitysystem;
use JunKR\CrossUtils;
use pocketmine\block\BlockIds;
use pocketmine\block\Crops;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\Player;
use pocketmine\plugin\MethodEventExecutor;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;

class Gift2022 extends PluginBase implements Listener{

    use SingletonTrait;

    public function onLoad() : void{
        self::setInstance($this);
    }

    public array $db = [];

    /**
     * @throws Exception
     */
    public function onEnable() : void{
        $this->db = CrossUtils::getDataArray($this->getDataFolder() . 'data.json');

        $this->getServer()->getPluginManager()->registerEvent(BlockBreakEvent::class, $this, EventPriority::MONITOR, new MethodEventExecutor('onBreak'), $this);
        $this->getServer()->getPluginManager()->registerEvent(DataPacketReceiveEvent::class, $this, EventPriority::MONITOR, new MethodEventExecutor('onDataPacket'), $this);
    }

    /**
     * @throws JsonException
     */
    public function onDisable() : void{
        file_put_contents($this->getDataFolder() . 'data.json', json_encode($this->db, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @throws Exception
     */
    public function onBreak(BlockBreakEvent $ev) : void{
        if($ev->isCancelled()){
            return;
        }

        $rand = random_int(0, 1000);

        // 0.3%
        if($rand > 3){
            return;
        }

        $block = $ev->getBlock();
        if(!($block instanceof Crops || $block->getId() === BlockIds::NETHER_WART_PLANT)){
            return;
        }

        $player = $ev->getPlayer();

        if(isset($this->db[$player->getName()]) && count($this->db[$player->getName()]) > 2){
            return;
        }

        $this->giveChanger($player);
    }

    /**
     * @throws JsonException
     */
    public function onDataPacket(DataPacketReceiveEvent $ev) : void{
        $packet = $ev->getPacket();

        if(!$packet instanceof ModalFormResponsePacket){
            return;
        }

        if($packet->formId !== 9999999){
            return;
        }

        $player = $ev->getPlayer();

        if(!isset($this->form_datas[$player->getName()])){
            return;
        }

        $val = json_decode($packet->formData, true, 512, JSON_THROW_ON_ERROR);

        if($val === 0){
            unset($this->form_datas[$player->getName()]);
            return;
        }

        $this->notice_form($player, ($form_data = $this->form_datas[$player->getName()])[0], $form_data[1], $form_data[2]);
    }

    /**
     * @throws JsonException
     */
    public function notice_form(Player $player, $code, $etc, $form_etc) : void{
        $notice_form = new ButtonForm(null);

        $notice_form->setTitle('§c§l[§e2022 선물교환권§c]');
        $notice_form->setContent("\n§c§l크로스팜과 함께하는 §e2022 설날!\n\n§f크로스팜의 교환권§r§7§o({$code['type']})§r§f에 당첨되셨어요!\n\n" . "\n§a§l크로스팜 교환권\n§r§f크로스팜이 여러분들께 선물을\n" . "§f준비했어요!\n\n§ehttps://www.crsbe.kr/2022gift\n§f에 브라우저로 접속하여\n\n" . '§e§l' . $code['code'] . "\n§r§f위 코드를 입력해보세요!\n\n유효기간 만료되기 전에 빨리 해보세요!" . $etc . $form_etc);

        $notice_form->addButton("§l§a확인 했어요.\n§r§8창을 닫을게요.");

        // 원래 API 는 창을 닫으면 호출을 안해서. 패킷으로.

        $pk = new ModalFormRequestPacket();
        $pk->formId = 9999999;
        $pk->formData = json_encode($notice_form->jsonSerialize(), JSON_THROW_ON_ERROR);
        $player->sendDataPacket($pk);
    }

    public array $form_datas = [];

    /**
     * @throws JsonException
     */
    public function onCompletion(string $player_, string $code) : void{
        $this->db[$player_][] = $code;

        $player = $this->getServer()->getPlayerExact($player_);
        if($player === null){
            return;
        }

        $code = json_decode($code, true, 512, JSON_THROW_ON_ERROR);
        $code['code'] = wordwrap($code['code'], 4, '-', true);

        $etc = '';
        if(communitysystem::getInstance()->getStatus($player)){
            $this->getServer()->getAsyncPool()->submitTask(new class($player->getName(), $code['type'], $code['code']) extends AsyncTask{

                public function __construct(public string $name, public string $type, public string $code){
                }

                public function onRun() : void{
                    $ch = curl_init(); // 리소스 초기화

                    $url = 'https://www.crsbe.kr/2022gift/dm?to=' . $this->name . '&type=' . urlencode($this->type) . '&code=' . $this->code;

                    // 옵션 설정
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

                    curl_exec($ch);

                    curl_close($ch);  // 리소스 해제
                }

            });
            $etc = "\n\n§9§l디스코드 인증이 되어있으시네요!\n§r§f디스코드로도 교환권번호가\n§f발송되었어요!\n§7§o(DM허용이 꺼져있으면 발송되지 않을 수 있음)";
        }

        $item = Item::get(11013);
        $item->setCustomName('§r§c§l[§e2022 선물교환권§c] §r§f' . $code['type'] . "\n§a§l크로스팜 교환권\n§r§f크로스팜이 여러분들께 선물을\n" . "§f준비했어요!\n\n§ehttps://www.crsbe.kr/2022gift\n§f에 브라우저로 접속하여\n\n" . '§e§l' . $code['code'] . "\n§r§f위 코드를 입력해보세요!\n\n유효기간 만료되기 전에 빨리 해보세요!" . $etc);

        $form_etc = '';

        if(!$player->getInventory()->canAddItem($item)){
            $form_etc = "\n\n§r§f❗❗❗❗❗❗❗❗❗❗❗❗❗❗\n§c§l!!!!!!! 주 의 !!!!!!!\n\n§r§f유저님의 인벤토리에 공간이 부족합니다!!\n선물교환권이 인벤토리에 들어가지 않았습니다!!\n\n이 화면을 스크린샷 찍으세요!!\n코드는 복구받을 수 없습니다!!";
        }

        $player->getInventory()->addItem($item);

        $this->form_datas[$player->getName()] = [$code, $etc, $form_etc];
        $this->notice_form($player, $code, $etc, $form_etc);
    }

    public function giveChanger(Player $player) : void{
        $this->getServer()->getAsyncPool()->submitTask(new class($player->getName()) extends AsyncTask{

            public string $code;

            public function __construct(public string $name){
            }

            public function onRun() : void{
                $ch = curl_init(); // 리소스 초기화

                $url = 'https://www.crsbe.kr/2022gift/get__id?token=ccrsdkgjklsdgjkdjslgsjdlkjklsdjk';

                // 옵션 설정
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

                $this->code = curl_exec($ch);

                curl_close($ch);  // 리소스 해제
            }

            public function onCompletion(Server $server) : void{
                if(!isset($this->code)){
                    return;
                }
                Gift2022::getInstance()->onCompletion($this->name, $this->code);
            }

        });
    }

}