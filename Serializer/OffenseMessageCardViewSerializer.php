<?php

namespace App\Serializer;

use App\Entity\Api\Individual;
use App\Entity\OffenseMessageCard;

class OffenseMessageCardViewSerializer
{
    /**
     * Сериализация карточки сообщения о правонарушении в массив для её просмотра
     * @param OffenseMessageCard $card
     * @param Individual|null $individual
     * @return array
     */
    final public function serialize(OffenseMessageCard $card, ?Individual $individual): array
    {
        $data = [];
        $data['id'] = $card->getId();
        $data['cardSerial'] = $card->getCardSerial();
        $data['description'] = $card->getDescription();
        $data['createdAt'] = $card->getCreatedAt();
        $data['status'] = $card->getStatus();
        $data['resolution'] = $card->getResolution();
        $data['executor'] = [
            'id' => $card->getExecutor()->getId(),
            'full_name' => empty($card->getExecutor())
                ? ''
                : $card->getExecutor()->getFIO(),
            'rank' => $card->getExecutor()->getRank()->getValue(),
            'division' => $card->getExecutor()->getDivision()->getName()
        ];
        $data['legalEntity'] = [
            'name' => $card->getOffenseMessageRequest()->getLegalEntity()->getName(),
            'inn' => $card->getOffenseMessageRequest()->getLegalEntity()->getInn(),
            'address' => $card->getOffenseMessageRequest()->getLegalEntity()->getAddress()
        ];
        $data['legalEntityContacts'] = [
            'requestExecutorName' => $card->getOffenseMessageRequest()->getExecutorName(),
        ];
        $contacts = $card->getOffenseMessageRequest()->getLegalEntity()->getContacts();
        foreach ($contacts as $contact) {
            $data['legalEntityContacts']['contacts'][] = [
                'type' => $contact->getContactType()->getName(),
                'content' => $contact->getContent()
            ];
        }
        if (!empty($individual)) {
            $data['violators'][] = [
                'fullName' => $individual->getFullName()['full_name'] ?? '',
                'email' => '',
                'phone' => '',
                'address' => $individual->getActualAddressOfResidence()
                    ?? $individual->getTemporaryRegistrationAddress()
                    ?? $individual->getPermanentRegistrationAddress()
                    ?? ''
            ];
        }
        $data['violations'][] = [
            'violation_type' => $card->getViolationType(),
            'violation_description' => $card->getViolationDescription(),
            'criminal_code_article_code' => $card->getCriminalCodeArticleCode()->getName(),
        ];
        $data['attachments'] = [];

        $attachments = $card->getAttachment();
        foreach ($attachments as $attachment) {
            $data['attachments'][] = [
                'fileId' => $attachment['fileId'],
                'fileName' => $attachment['fileName'],
                'description' => $attachment['description']
            ];
        }

        return $data;
    }
}
