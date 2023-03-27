<?php

namespace App\Serializer\Normalizer;

use App\Entity\User;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;

class UserNormalizer implements ContextAwareNormalizerInterface
{
    public function __construct(private RoleHierarchyInterface $roleHierarchy)
    {
    }

    /**
     * @param User $object
     * @param $format
     * @param array $context
     * @return array
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        return [
            'id' => $object->getId(),
            'username' => $object->getUserIdentifier(),
            'fio' => $object->getFIO(),
            'firstName' => $object->getFirstName(),
            'lastName' => $object->getLastName(),
            'patronymic' => $object->getPatronymic(),
            'roles' => $this->roleHierarchy->getReachableRoleNames($object->getRoles()),
        ];
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof User &&
               in_array('profile', $context[AbstractNormalizer::GROUPS] ?? []);
    }
}
