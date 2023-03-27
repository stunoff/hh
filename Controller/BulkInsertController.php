<?php

namespace App\Controller;

use App\Entity\DictionaryElementSub\AccessLevel;
use App\Entity\DictionaryElementSub\LinkIndividualIndividualType;
use App\Entity\DictionaryElementSub\LinkItemItemType;
use App\Entity\ObjectLink;
use App\Entity\RegisteredItem;
use App\Form\BulkInsertType;
use App\Form\RegisteredItemType;
use App\Repository\DictionaryElementRepository;
use App\Repository\RegisteredItemRepository;
use App\Service\IndividualsApi;
use App\Service\NodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class BulkInsertController extends AbstractController
{
    #[Route('/bulk/insert', name: 'bulk_insert')]
    public function index(): Response
    {
        $form = $this->createForm(BulkInsertType::class, null, ['action' => $this->generateUrl('bulk_insert_step_2')]);

        return $this->render('bulk_insert/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/bulk/step-2', name: 'bulk_insert_step_2', methods: ['POST'])]
    public function step2(Request $request): Response
    {
        $form = $this->createForm(BulkInsertType::class, null, ['action' => $this->generateUrl('bulk_insert_step_2')]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var UploadedFile $file
             */
            $file = $form->get('filename')->getData();
            $filename = $file->getClientOriginalName();
            $filesize = $file->getSize();
        }
        $hash = hash('sha256', $filename);
        switch ($hash) {
            case '0e1275d3f487fe1fc935cd8055462134da1bbe6ffd9424caf683c560de964a4d':
                $strings = 3;
                break;
            case '6b08fc1019f2aa08f983fc9cfa96dc9891454e48c6859fb465ffd201dfc98e7d':
                $strings = 2;
                break;
            case '62d818bdbbdb5d93af3629cf4e4d960c3cdd46f5774da5c037d2df052a51ec87':
                $strings = 2;
                break;
            case '92a344d1367d1b4224bbbe106fd96655cf89dfbafacf84c84a26f05d83c17855':
                $strings = 2;
                // no break
            default:
                $strings = 2;
        }

        return $this->render('bulk_insert/step2.html.twig', [
            'filename' => $filename,
            'strings' => $strings,
            'hash' => $hash,
            'filesize' => $filesize,
        ]);
    }

    #[Route('/bulk/finish/{hash}', name: 'bulk_insert_finish')]
    public function step3(
        $hash,
        IndividualsApi $api,
        EntityManagerInterface $entityManager,
        DictionaryElementRepository $dictionaryElementRepository,
        NodeService $nodeService,
        RegisteredItemRepository $repo,
        RegisteredItemType $registeredItemType
    ): Response {
        if ($hash === '0e1275d3f487fe1fc935cd8055462134da1bbe6ffd9424caf683c560de964a4d') { // 59.1
            $individualsData = [
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Иван',
                            'last_name' => 'Иванов',
                            'patronymic' => 'Иванович',
                        ],
                        'birthday' => '07.02.1996',
                        'identity' => [
                            'number' => '1234 123456',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '31.07.2014',
                        ],
                        'citizenship' => [
                            'РФ',
                        ],
                        'gender' => "1",
                        'actual_address_of_residence' => [
                            'address_text' => 'Россия, Москва, Полянка, 32-23',
                            'fias_id' => '',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Петр',
                            'last_name' => 'Петров',
                            'patronymic' => 'Петрович',
                        ],
                        'birthday' => '04.03.1994',
                        'identity' => [
                            'number' => '5678 789456',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '13.01.2015',
                        ],
                        'citizenship' => [
                            'РФ',
                        ],
                        'gender' => "1",
                        'actual_address_of_residence' => [
                            'address_text' => 'Россия, г. Москва, ул. Учинская, 1',
                            'fias_id' => '',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Петр',
                            'last_name' => 'Петров',
                            'patronymic' => 'Петрович',
                        ],
                        'birthday' => '04.03.1994',
                        'identity' => [
                            'number' => '5678 789456',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '13.01.2015',
                        ],
                        'citizenship' => [
                            'РФ',
                        ],
                        'gender' => "1",
                        'actual_address_of_residence' => [
                            'address_text' => 'Россия, г. Москва, ул. Учинская, 1',
                            'fias_id' => '',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
            ];
            $registeredItemsData = [
                [
                    'Дата совершения правонарушения' => '08.01.2020',
                    'Дата составления протокола' => '08.01.2020',
                    'Территориальный орган' => 'ОП №9 на ММ',
                    'Подразделение составителя' => 'ППС',
                    'Подразделение выявившего' => 'ППС',
                    'Вид нарушителя' => 'Физическое лицо',
                    'Кодекс' => 'КоАП РФ',
                    'Статья' => '18.8',
                    'Часть' => '3.1',
                    'Адрес места совершения правонарушения' => 'Россия, г. Москва, ул. Совхозная, 45',
                    'Основное наказание' => 'Административный штраф',
                    'Статус основного наказания' => 'Исполнено',
                    'Штраф, руб.' => '5000',
                    'Статус штрафа' => 'Оплачен',
                    'Сумма взыскания, руб.' => '5000',
                    'Дополнительное наказание' => 'Выдворение',
                    'Статус дополнительного наказания' => 'Исполнено',
                    'Кем вынесено постановление' => 'Суд',
                    'Номер протокола' => 'ММ 999999',
                ],
                [
                    'Дата совершения правонарушения' => '09.01.2020',
                    'Дата составления протокола' => '09.01.2020',
                    'Территориальный орган' => 'ОМВД России по Дмитровскому району г. Москвы',
                    'Подразделение составителя' => 'ДЧ',
                    'Подразделение выявившего' => 'ППС',
                    'Вид нарушителя' => 'Физическое лицо',
                    'Кодекс' => 'КоАП РФ',
                    'Статья' => '18.8',
                    'Часть' => '3.1',
                    'Адрес места совершения правонарушения' => 'Россия, г. Москва, ул. Учинская, 1',
                    'Основное наказание' => 'Административный штраф',
                    'Статус основного наказания' => 'Исполнено',
                    'Штраф, руб.' => '5000',
                    'Статус штрафа' => 'Оплачен',
                    'Сумма взыскания, руб.' => '5000',
                    'Дополнительное наказание' => 'Выдворение',
                    'Статус дополнительного наказания' => 'Исполнено',
                    'Кем вынесено постановление' => 'Суд',
                    'Номер протокола' => 'САО 999999',
                ],
                [
                    'Дата совершения правонарушения' => '08.01.2020',
                    'Дата составления протокола' => '08.01.2020',
                    'Территориальный орган' => 'ОП №9 на ММ',
                    'Подразделение составителя' => 'ППС',
                    'Подразделение выявившего' => 'ППС',
                    'Вид нарушителя' => 'Физическое лицо',
                    'Кодекс' => 'КоАП РФ',
                    'Статья' => '18.8',
                    'Часть' => '3.1',
                    'Адрес места совершения правонарушения' => 'Россия, г. Москва, ул. Совхозная, 45',
                    'Основное наказание' => 'Административный штраф',
                    'Статус основного наказания' => 'Исполнено',
                    'Штраф, руб.' => '5000',
                    'Статус штрафа' => 'Оплачен',
                    'Сумма взыскания, руб.' => '5000',
                    'Дополнительное наказание' => 'Выдворение',
                    'Статус дополнительного наказания' => 'Исполнено',
                    'Кем вынесено постановление' => 'Суд',
                    'Номер протокола' => 'ММ 999999',
                ],

            ];

            //create individuals:
            $individualsIds = $this->createIndividuals($api, $individualsData);

            // create RIs
            $registeredItemsIds = $this->createRegisteredItems(
                $entityManager,
                $nodeService,
                $repo,
                $dictionaryElementRepository,
                $registeredItemType,
                $registeredItemsData
            );

            // now create link between RI0 and RI2
            $link = new ObjectLink();
            $link->setType(ObjectLink::Type_RegisteredItemToItem)
                ->setCreatedBy($this->getUser())
                ->setCreatedTs(new \DateTime())
                ->setLeftObjectId($registeredItemsIds[0])
                ->setRightObjectId($registeredItemsIds[2])
                ->setSubtype(LinkItemItemType::DUPLICATE)
                ->setComment('пакетное создание');
            $entityManager->persist($link);
            $entityManager->flush();

            // now create link between i1 and i2
            $link = new ObjectLink();
            $link->setType(ObjectLink::Type_IndividualToIndividual)
                ->setCreatedBy($this->getUser())
                ->setCreatedTs(new \DateTime())
                ->setLeftObjectId($individualsIds[1]['individual_id'])
                ->setRightObjectId($individualsIds[2]['individual_id'])
                ->setSubtype(LinkIndividualIndividualType::DUPLICATE)
                ->setComment('пакетное создание');
            $entityManager->persist($link);
            $entityManager->flush();

            $this->createIRlinks($entityManager, $individualsIds, $registeredItemsIds);
            $idoubles = 1;
            $rdoubles = 1;
            $links = 3;
        } elseif ($hash === '6b08fc1019f2aa08f983fc9cfa96dc9891454e48c6859fb465ffd201dfc98e7d') {
            $registeredItemsData = [
                [
                    'VIN' => 'VF3PNCFB088656657',
                    'марка' => 'PEUGEOT 107',
                    'год выпуска' => '2012',
                    'цвет' => 'белый',
                    'кузов' => 'VF3PNCFB088656657',
                    'двигатель' => 'VF3PNCFB088656657',
                    'шасси' => 'отсутствует',
                    'причина розыска' => 'угон',
                    'владелец' => 'ТЕСТОВЫЙ ТЕСТ ТЕСТОВИЧ',
                    'дата совершения правонарушения' => '01.01.2020',
                ],
                [
                    'VIN' => 'VF7RCRFJC76658482',
                    'марка' => 'СИТРОЕН С5',
                    'год выпуска' => '2005',
                    'цвет' => 'темно-коричневый',
                    'кузов' => 'xxxxxxxxx',
                    'двигатель' => 'xxxxxxxxx',
                    'шасси' => 'xxxxxxxxxxx',
                    'причина розыска' => 'Нарушение ПДД',
                    'владелец' => 'Cитроенов Сидр Сидорович',
                    'дата совершения правонарушения' => '14.03.2021',
                ],
            ];
            $individualsData = [
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Тест',
                            'last_name' => 'Тестовый',
                            'patronymic' => 'Тестович',
                        ],
                        'birthday' => '12.02.2000',
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Сидр',
                            'last_name' => 'Ситроенов',
                            'patronymic' => 'Сидорович',
                        ],
                        'birthday' => '12.02.2000',
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
            ];

            $individualsIds = $this->createIndividuals($api, $individualsData);
            $registeredItemsIds = $this->createRegisteredItems(
                $entityManager,
                $nodeService,
                $repo,
                $dictionaryElementRepository,
                $registeredItemType,
                $registeredItemsData
            );

            // now create links I -> R
            $this->createIRlinks($entityManager, $individualsIds, $registeredItemsIds);

            $idoubles = 0;
            $rdoubles = 0;
            $links = 2;
        } elseif ($hash == '62d818bdbbdb5d93af3629cf4e4d960c3cdd46f5774da5c037d2df052a51ec87') {
            $registeredItemsData = [
                [
                    'дата оперативного учета' => '12.05.2020',
                    'первичный документ' => 'SIM-карта',
                    'телефон инициатора' => '+7 (915) 123-45-67',
                ],
                [
                    'дата оперативного учета' => '08.11.2019',
                    'первичный документ' => 'SIM-карта',
                    'телефон инициатора' => '+7 (917) 555-22-33',
                ],

            ];
            $individualsData = [
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Сергей',
                            'last_name' => 'Петров',
                            'patronymic' => 'Сергеевич',
                        ],
                        'identity' => [
                            'number' => '5454 123456',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '01.06.2015',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Макар',
                            'last_name' => 'Сидоров',
                            'patronymic' => 'Семенович',
                        ],
                        'identity' => [
                            'number' => '1234 321654',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '05.05.2010',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],

            ];
            //create individuals:
            $individualsIds = $this->createIndividuals($api, $individualsData);
            $this->get('session')->set('individualIdP', $individualsIds[0]);
            // create RIs
            $registeredItemsIds = $this->createRegisteredItems(
                $entityManager,
                $nodeService,
                $repo,
                $dictionaryElementRepository,
                $registeredItemType,
                $registeredItemsData
            );

            $this->createIRlinks($entityManager, $individualsIds, $registeredItemsIds);
            $idoubles = 0;
            $rdoubles = 0;
            $links = 2;
        } elseif ($hash == '92a344d1367d1b4224bbbe106fd96655cf89dfbafacf84c84a26f05d83c17855') {
            $registeredItemsData = [
                [
                    'дата оперативного учета' => '12.05.2020',
                    'первичный документ' => 'SIM-карта',
                    'телефон инициатора' => '+7 (915) 123-45-67',
                ],
                [
                    'дата оперативного учета' => '08.11.2019',
                    'первичный документ' => 'SIM-карта',
                    'телефон инициатора' => '+7 (917) 555-22-34',
                ],

            ];
            $individualsData = [
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Глафира',
                            'last_name' => 'Петрова',
                            'patronymic' => 'Сергеевна',
                        ],
                        'identity' => [
                            'number' => '5455 123457',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '01.06.2015',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Анастасия',
                            'last_name' => 'Сидорова',
                            'patronymic' => 'Сергеевна',
                        ],
                        'identity' => [
                            'number' => '1235 321655',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '05.05.2010',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],

            ];
            //create individuals:
            $individualsIds = $this->createIndividuals($api, $individualsData);

            // create RIs
            $registeredItemsIds = $this->createRegisteredItems(
                $entityManager,
                $nodeService,
                $repo,
                $dictionaryElementRepository,
                $registeredItemType,
                $registeredItemsData
            );

            $this->createIRlinks($entityManager, $individualsIds, $registeredItemsIds);
            $idoubles = 0;
            $rdoubles = 0;
            $links = 2;
        } elseif ($hash == '943f73c2db96e13593636f9d8954408e656a05f849e56276b4109ade640cca57') {
            $registeredItemsIds = [];
            $individualsData = [
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Сергей',
                            'last_name' => 'Петров',
                            'patronymic' => 'Сергеевич',
                        ],
                        'identity' => [
                            'number' => '5454 123456',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '01.06.2015',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
                [
                    'user_id' => $this->getUser()->getId(),
                    'individual' => [
                        'individual_id' => (string) Uuid::v4(),
                        'accesslvl_id' => AccessLevel::LEVEL_3,
                        'full_name' => [
                            'first_name' => 'Сергей',
                            'last_name' => 'Петров',
                            'patronymic' => 'Сергеевич',
                        ],
                        'identity' => [
                            'number' => '5454 123456',
                            'issue_place' => 'ОВД МР ХАМОВНИКИ Г. МОСКВЫ',
                            'issue_date' => '01.06.2015',
                        ],
                        'is_deleted' => false,
                        'is_archived' => false,
                    ]
                ],
            ];
            $individualsIds = $this->createIndividuals($api, $individualsData);

            // now create link between i1 and i2
            $link = new ObjectLink();
            $link->setType(ObjectLink::Type_IndividualToIndividual)
                ->setCreatedBy($this->getUser())
                ->setCreatedTs(new \DateTime())
                ->setLeftObjectId($individualsIds[0]['individual_id'])
                ->setRightObjectId($individualsIds[1]['individual_id'])
                ->setSubtype(LinkIndividualIndividualType::DUPLICATE)
                ->setComment('пакетное создание');
            $entityManager->persist($link);
            $entityManager->flush();

            $originalId = $this->get('session')->get('individualIdP')['individual_id'];
            $link = new ObjectLink();
            $link->setType(ObjectLink::Type_IndividualToIndividual)
                ->setCreatedBy($this->getUser())
                ->setCreatedTs(new \DateTime())
                ->setLeftObjectId($originalId)
                ->setRightObjectId($individualsIds[0]['individual_id'])
                ->setSubtype(LinkIndividualIndividualType::DUPLICATE)
                ->setComment('пакетное создание');
            $entityManager->persist($link);
            $entityManager->flush();

            $idoubles = 2;
            $rdoubles = 0;
            $links = 3;
        }
        return $this->render('bulk_insert/finish.html.twig', [
            'individualsIds' => $individualsIds,
            'registeredItems' => $registeredItemsIds,
            'iDoubles' => $idoubles,
            'rDoubles' => $rdoubles,
            'links' => $links,
        ]);
    }

    private function createIRlinks(
        EntityManagerInterface $entityManager,
        array $individualsIds,
        array $registeredItemsIds
    ) {
        // now create link I->R
        foreach ($individualsIds as $key => $individualsId) {
            $link = new ObjectLink();
            $link->setType(ObjectLink::Type_RegisteredItemToIndividual)
                ->setCreatedBy($this->getUser())
                ->setCreatedTs(new \DateTime())
                ->setLeftObjectId($registeredItemsIds[$key])
                ->setRightObjectId($individualsIds[$key]['individual_id'])
                ->setComment('пакетное создание');
            $entityManager->persist($link);
            $entityManager->flush();
        }
    }

    private function createIndividuals($api, array $individualsData,)
    {
        $individualsIds = [];
        foreach ($individualsData as $item) {
            $individualsIds[] = $api->request(
                'create_individual',
                $item,
                'POST'
            );
        }
        return $individualsIds;
    }

    private function createRegisteredItems(
        EntityManagerInterface $entityManager,
        NodeService $nodeService,
        RegisteredItemRepository $repo,
        DictionaryElementRepository $dictionaryElementRepository,
        RegisteredItemType $registeredItemType,
        array $registeredItemsData
    ) {
        $registeredItemsIds = [];
        $fieldsConfig = $registeredItemType->getFieldsConfig()['data']['fields'];
        foreach ($registeredItemsData as $registeredItem) {
            $transformedFields = [];
            foreach ($registeredItem as $key => $value) {
                $matched = false;
                foreach ($fieldsConfig as $fcKey => $fcValue) {
                    if (mb_strtolower($fcValue['title']) == mb_strtolower($key)) {
                        $transformedFields[$fcKey] = $value;
                        $matched = true;
                    }
                }
                if (!$matched) { // default text field
                    $transformedFields['3e01829e-1b03-4f10-a7c8-79c86619e10f'][] = $key . ': ' . $value;
                }
            }
            $rItem = new RegisteredItem();
            $id = (string) Uuid::v4();
            $rItem->setCreatedTs(new \DateTime())
                ->setCreatedBy($this->getUser())
                ->setAccesslvl($dictionaryElementRepository->findOneBy(['id' => AccessLevel::LEVEL_3]))
                ->setCategory($dictionaryElementRepository->findOneBy(['code' => 'ОУ_КОНТРОЛЬНЫЙ_СПИСОК']))
                ->setCode($repo->generateItemCode())
                ->setNode($nodeService->getCurrentNode())
                ->setIsArchived(false)
                ->setIsDeleted(false)
                ->setItemId($id)
                ->setIsActiveVersion(true)
                ->setItemCreatedAt(new \DateTime())
                ->setData($transformedFields);
            $registeredItemsIds[] = $id;
            $entityManager->persist($rItem);
            $entityManager->flush();
        }
        return $registeredItemsIds;
    }
}
