<?php

namespace Topxia\Service\Card;

interface CardService
{
    public function addCard($card);

    public function getCard($id);

    public function getCardByCardIdAndCardType($cardId, $cardType);

    public function updateCardByCardIdAndCardType($cardId, $cardType, $fields);

    public function findCardsByUserIdAndCardType($userId, $cardType);

    public function findCardDetailByCardTypeAndCardId($cardType, $id);

    public function findCardDetailsByCardTypeAndCardIds($cardType, $ids);
}
