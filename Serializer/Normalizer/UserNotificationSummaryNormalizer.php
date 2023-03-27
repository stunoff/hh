<?php

namespace App\Serializer\Normalizer;

use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class UserNotificationSummaryNormalizer implements ContextAwareNormalizerInterface
{
    public const SUMMARY_CONTEXT = 'summary_context';

    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = []): array
    {
        $notification = $this->normalizer->normalize($object, $format, $context);

        $notification['type'] = [
            'id' => $notification['type']['id'],
            'code' => $notification['type']['code'],
            'description' => $notification['type']['description'],
        ];
        if ($notification['dstUser']) {
            $notification['dst_user'] = $notification['dstUser']['id'];
            unset($notification['dstUser']);
        }

        return $notification;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof \App\Entity\UserNotification && in_array(self::SUMMARY_CONTEXT, $context);
    }
}

