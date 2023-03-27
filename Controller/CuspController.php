<?php

namespace App\Controller;

use App\Entity\LegalsMirror;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\CuspRepository;
use App\Repository\CuspParticipantsRepository;
use App\Entity\Cusp;
use App\Form\CuspType;
use App\Form\EditCuspType;
use App\Form\IndividualType;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\IndividualsApi;
use App\Entity\Api\Individual;
use App\Service\FormatIndividualService;
use App\Repository\DataChangelogRepository;
use App\Service\ActivityLogService;
use App\Widget\IndividualsWidget;
use App\Entity\CuspParticipants;
use App\Repository\DocumentsRepository;
use App\Service\EntityGroupByService;
use App\Service\ArrayGroupByService;
use App\Repository\LegalsMirrorRepository;
use App\Repository\CuspRegisteredItemsRepository;
use App\Repository\TemplateElementsRepository;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Documents;
use App\Entity\DocumentVersion;
use App\Repository\DirectoryElementRepository;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Entity\DirectoryElement;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use App\Service\JsonFormSerializers;
use App\Repository\DocumentVersionRepository;
use App\Utils\Directory\Directory;

class CuspController extends AbstractController
{
    private array $changelogSkipFields = [
        'created_ts',
        'deactivated_ts',
        'created_by',
        'record_id',
        'is_active_version'
    ];

    #[Route('/cusps', name: 'cusps')]
    public function index(
        Request $request,
        CuspRepository $cuspRepository,
        DirectoryElementRepository $dictRepository,
        ArrayGroupByService $arrayGroupByService,
        EntityGroupByService $entityGroupByService,
        IndividualsApi $api,
        IndividualsWidget $individualsWidget,
        JsonFormSerializers $serializers
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_VIEW_CUSPS');
        $declarant_role_id = $dictRepository->findOneBy([
            'directory' => 'participant_types',
            'name' => 'Заявитель'
        ])->getId();
        $cusps = $cuspRepository->findByCreatorJoiningDeclarant($this->getUser()->getId(), $declarant_role_id);
        $cuspsByDeclarant = $arrayGroupByService->groupElementsByField($cusps, 'declarant');
        $declarant_ids = array_keys($cuspsByDeclarant);
        $userAccessLevel = $this->getUser()->getAllowedAccessLevelIds();
        $orderBy = $this->get('session')->get('individualsListOrderBy') ?? $request->get('orderBy') ?? 'full_name,asc';
        $queryParams = $serializers->queryParams($request, 'access_levels', $userAccessLevel, $orderBy);
        $queryParams['individual_id'] = $declarant_ids;
        $queryParams['offset'] = 0;
        try {
            $declarantsData = $api
                ->requestIntoObjectList('get_individual_list_basic', $queryParams, Individual::class);
        } catch (\Exception $e) {
            $this->addFlash(
                'warning',
                'БД физ лиц не функционирует или вернула неверный ответ.'
            );
            return $this->render('cusp/index.html.twig', [
                'cusps' => [],
            ]);
        }
        $groupedDeclarantsData = $entityGroupByService
            ->groupObjectsByField($declarantsData, 'individual_id', $individualsWidget);

        foreach ($cuspsByDeclarant as $key => $cusp) {
            if (!isset($groupedDeclarantsData[$key])) {
                $cuspsByDeclarant[$key][0]['declarant'] = 'Данные удалены из базы';
            } else {
                $declarantData = $groupedDeclarantsData[$key][0];
                $declarantFIO = $declarantData['full_name'];
                $cuspsByDeclarant[$key][0]['declarant'] = $declarantFIO['last_name'] .
                    ' ' . $declarantFIO['first_name'] .
                    ' ' . $declarantFIO['patronymic'] .
                    ' ' . $declarantData['birthday'] .
                    ' г.р.';
            }
        }
        return $this->render('cusp/index.html.twig', [
            'cusps' => $cuspsByDeclarant
        ]);
    }

    #[Route('/cusps/new', name: 'new_cusp', methods: ['GET', 'POST'])]
    public function createCusp(
        Request $request,
        EntityManagerInterface $em,
        FormatIndividualService $formatService,
        DataChangelogRepository $changelog,
        ActivityLogService $activityLog,
        IndividualsApi $api,
        DirectoryElementRepository $dictRepository,
        IndividualsWidget $individualsWidget
    ) {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_CREATE_CUSP');
        $cusp = new Cusp();
        $individual = new Individual();
        $cusp->setCreator($this->getUser());
        $form = $this->createForm(CuspType::class, $cusp);
        $individuals_form = $this->createForm(IndividualType::class, $individual, array('csrf_protection' => false));
        $form->handleRequest($request);
        $requestCopy = $request->request->all();
        if ($form->isSubmitted() && $form->isValid()) {
            $participant = new CuspParticipants();
            if (!isset($requestCopy['cusp']['declarant'])) {
                $individuals_form->handleRequest($request);
                $user = $this->getUser()->getId();
                try {
                    $response = $api->request(
                        'create_individual',
                        $formatService->format($individual, $individualsWidget, $individuals_form, $user),
                        'POST'
                    );
                    $individual->setProperties(['individual_id' => $response['individual_id']]);
                    $formatService->logActivity($changelog, $activityLog, $individual, $individualsWidget, null, $user);
                    $participant->setParticipantId($response['individual_id']);
                } catch (\Exception $e) {
                    $this->addFlash(
                        'warning',
                        'БД физ лиц не функционирует или вернула неверный ответ.'
                    );
                    return $this->render('cusp/index.html.twig', [
                        'cusps' => [],
                    ]);
                }
            } else {
                $participant->setParticipantId($requestCopy['cusp']['declarant']);
            }
            $participant->setParticipantClass(Individual::class);
            $declarant_role_id = $dictRepository->findOneBy([
                'directory' => 'participant_types',
                'name' => 'Заявитель'
            ]);
            $participant->setParticipantType($declarant_role_id);
            $cusp->addCuspParticipant($participant);
            if ($cusp->getExpDate() == null) {
                $expDate = clone $cusp->getCreatedAt();
                $expDate->modify('+ 2 days');
                $cusp->setExpDate($expDate);
            }
            $em->persist($participant);
            $em->persist($cusp);
            $em->flush();
            return $this->redirectToRoute('cusps');
        }
        return $this->render('cusp/new.html.twig', [
            'form' => $form->createView(),
            'individuals_form' => $individuals_form->createView()
        ]);
    }

    #[Route('/cusps/edit/{slug}', name: 'edit_cusp', methods: ['GET', 'POST'])]
    public function editCusp(
        Request $request,
        CuspRepository $cuspRepository,
        CuspParticipantsRepository $cuspParticipantsRepository,
        IndividualsApi $api,
        DirectoryElementRepository $dictRepository,
        EntityManagerInterface $em,
        JsonFormSerializers $serializers,
        Directory $directory
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_EDIT_CUSP');
        $cusp_id = $request->get('slug');
        $cusp = $cuspRepository->find($cusp_id);
        $form = $this->createForm(EditCuspType::class, $cusp);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cusp);
            $em->flush();
            return $this->redirectToRoute('edit_cusp', ['slug' => $cusp_id]);
        }
        $node_id = (string)$this->getUser()->getNode()->getId();
        $directory->setCode('participant_types');
        $directory->setNodeId($node_id);
        $declarant_role_id = $directory->getValues()[' Заявитель'];
        $declarant_role = $dictRepository->find($declarant_role_id);
        $declarant_id = $cuspParticipantsRepository->findOneBy([
            'Cusp' => $cusp_id,
            'ParticipantType' => $declarant_role
        ])->getParticipantId();
        try {
            $declarant = $api->requestIntoObject('get_individual_basic', [
                    'individual_id' => (string)$declarant_id
                ], Individual::class);
            $declarant_as_text = $serializers->serializeDeclarant($declarant);
        } catch (\Exception $e) {
            $declarant_as_text = 'Данные удалены';
        }
        return $this->render('cusp/edit.html.twig', [
            'form' => $form->createView(),
            'cusp' => $cusp,
            'declarant' => $declarant_as_text
        ]);
    }

    #[Route('/cusps/{slug}/details', name: 'cusp_details', methods: ['GET', 'POST'])]
    public function cuspDetails(
        Request $request,
        CuspRepository $cuspRepository,
        DirectoryElementRepository $dictRepository,
        DocumentsRepository $docsRepository,
        CuspParticipantsRepository $participantsRepository,
        IndividualsApi $api,
        EntityGroupByService $groupByService,
        LegalsMirrorRepository $legalsRepository,
        IndividualsWidget $individualsWidget,
        CuspRegisteredItemsRepository $riRepository,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        FormatIndividualService $formatService,
        DataChangelogRepository $changelog,
        ActivityLogService $activityLog,
        JsonFormSerializers $serializers,
        Directory $directory,
        ArrayGroupByService $arraySevice
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_VIEW_CUSP');
        $cusp_id = $request->get('slug');
        $cusp = $cuspRepository->find($cusp_id);
        $user = $this->getUser();
        $node = (string)$user->getNode()->getId();
        $directory->setNodeId($node);
        $directory->setCode('document_types');
        $document_types = $directory->getValues(false);
        $document_types = $arraySevice->groupElementsByField($document_types, 'name');
        ksort($document_types);
        foreach ($document_types as $k => $type) {
            $document_types[$k][0]['value'] = json_decode($type[0]['value'], true);
        }
        $documentsWithVersions = $docsRepository->fetchDocumentsWithVersions($cusp);
        foreach ($documentsWithVersions as $k => $document) {
            $versions = $document->getDocumentVersions();
            foreach ($versions as $version) {
                if ($version->getIsActive()) {
                    $documentsWithVersions[$k]->setActiveVersion((string)$version->getId());
                }
            }
        }
        $userAccessLevel = $user->getAllowedAccessLevelIds();
        $orderBy = $this->get('session')->get('individualsListOrderBy') ?? $request->get('orderBy') ?? 'full_name,asc';
        $individuals = $serializers->provideParticipantsForTemlate(
            Individual::class,
            $cusp,
            $request,
            $participantsRepository,
            $groupByService,
            $legalsRepository,
            $api,
            $userAccessLevel,
            $orderBy,
            null,
            $individualsWidget
        );
        $legals = $serializers->provideParticipantsForTemlate(
            LegalsMirror::class,
            $cusp,
            $request,
            $participantsRepository,
            $groupByService,
            $legalsRepository,
            $api,
            $userAccessLevel,
            $orderBy,
            null,
            null
        );
        $node_id = (string)$this->getUser()->getNode()->getId();
        $directory->setCode('participant_types');
        $directory->setNodeId($node_id);
        $roles = $directory->getValues(false);
        $roles = $groupByService->groupObjectsByField($roles, 'name');
        $registeredItems = $riRepository->cuspRegisteredItems($cusp_id);
        $individual = new Individual();
        $documentForm = $this->get('form.factory')->createNamedBuilder(
            'resolutions',
            FormType::class,
            null,
            ['allow_extra_fields' => true]
        )
            ->add('document', HiddenType::class)
            ->add('resolution', FileType::class, [
                'label' => 'Выберите файл',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5120k',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/x-pdf',
                        ],
                        'mimeTypesMessage' => 'Загрузите файл в формате pdf размером не более 5Мб',
                    ])
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Загрузить'
            ])
            ->getForm();

        $personForm = $this->get('form.factory')->createNamedBuilder(
            'participant',
            FormType::class,
            null,
            ['allow_extra_fields' => true]
        )
            ->add('participantType', EntityType::class, [
                'class' => DirectoryElement::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('d')
                        ->where('d.directory=\'participant_types\'')
                        ->andwhere('not(d.name=\'Заявитель\')');
                },
                'label' => 'Тип участника'
            ])
            ->add('newPerson', IndividualType::class, [
                'label' => 'Поиск и добавление нового участника',
                'required' => false
            ])
            ->add('individual_id', HiddenType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'Добавить',
                'attr' => [
                    'class' => 'mt-3 btn-primary'
                ]
            ])
            ->getForm();
        $personForm->handleRequest($request);
        if ($personForm->isSubmitted() && $personForm->isValid()) {
            $participant = new CuspParticipants();
            if ($personForm->get('individual_id')->getData() !== null) {
                $participant->setParticipantId($personForm->get('individual_id')->getData());
            } else {
                $individual = new Individual();
                $normalizer = new ObjectNormalizer();
                $serializer = new Serializer([$normalizer]);
                $individual = $serializer->denormalize(
                    $personForm->get('newPerson')->getData(),
                    '\App\Entity\Api\Individual'
                );
                $user = $this->getUser()->getId();
                $response = $api->request(
                    'create_individual',
                    $formatService->format($individual, $individualsWidget, null, $user),
                    'POST'
                );
                $individual->setProperties(['individual_id' => $response['individual_id']]);
                $formatService->logActivity($changelog, $activityLog, $individual, $individualsWidget, null, $user);
                $participant->setParticipantId($response['individual_id']);
            }
            $participant->setParticipantClass(Individual::class);
            $participant->setParticipantType($personForm->get('participantType')->getData());
            $cusp->addCuspParticipant($participant);
            $em->persist($participant);
            $em->persist($cusp);
            $em->flush();
            unset($individual);
            unset($personForm);
            return $this->redirect($request->getUri());
        }
        $documentForm->handleRequest($request);
        if ($documentForm->isSubmitted() && $documentForm->isValid()) {
            $doc_id = $documentForm->get('document')->getData();
            $document = $docsRepository->find($doc_id);
            $resolution_file = $documentForm->get('resolution')->getData();
            if ($resolution_file) {
                $originalFilename = pathinfo($resolution_file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $resolution_file->guessExtension();
                try {
                    $resolution_file->move($this->getParameter('resolutions_dir'), $newFilename);
                } catch (FileException $e) {
                    throw $e;
                }
                $document->setResolution($newFilename);
                if ($document->getType()->getName() == 'Постановление о продлении срока проверки до 10 суток') {
                    $currCuspExpDate = $cusp->getExpDate();
                    $newExpDate = clone $currCuspExpDate;
                    $newExpDate->modify('+ 7 day');
                    $cusp->setExpDate($newExpDate);
                    $em->persist($cusp);
                    $this->addFlash(
                        'success',
                        'Срок истечения дела продлен до 10 дней'
                    );
                }
                if ($document->getType()->getName() == 'Постановление о продлении срока проверки до 30 суток') {
                    $currCuspExpDate = $cusp->getExpDate();
                    $newExpDate = clone $currCuspExpDate;
                    $newExpDate->modify('+ 27 day');
                    $cusp->setExpDate($newExpDate);
                    $em->persist($cusp);
                    $this->addFlash(
                        'success',
                        'Срок истечения дела продлен до 30 дней'
                    );
                }
                $em->persist($document);
                $em->flush();
            }
        } elseif ($documentForm->isSubmitted()) {
            $this->addFlash(
                'warning',
                'Не удалось загрузить файл'
            );
        }

        return $this->render('cusp/details.html.twig', [
            'cusp' => $cusp,
            'documents' => $documentsWithVersions,
            'individuals' => $individuals,
            'legals' => $legals,
            'roles' => $roles,
            'registeredItems' => $registeredItems,
            'documentTypes' => $document_types,
            'documentForm' => $documentForm->createView(),
            'personForm' => $personForm->createView()
        ]);
    }

    #[Route('/cusps/{slug}/document/new/{type}', name: 'new_cusp_document', methods: ['GET', 'POST'])]
    public function creteNewDocument(
        Request $request,
        TemplateElementsRepository $elementsRepository,
        EntityGroupByService $groupByService,
        CuspRepository $cuspRepository,
        EntityManagerInterface $entityManager,
        DirectoryElementRepository $dictRepository,
        JsonFormSerializers $serializers,
        Directory $directory
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_CREATE_DOCUMENT');
        $documentTypeId = $request->get('type');
        $dictTypeElement = $dictRepository->findOneBy(['id' => $documentTypeId]);
        $templateElements = $elementsRepository->findByType($dictTypeElement);
        $templateElementsByOrder = $groupByService->groupObjectsByField($templateElements, 'PlacementRegion');
        $node_id = (string)$this->getUser()->getNode()->getId();
        $directory->setCode('template_areas');
        $directory->setNodeId($node_id);
        $areaNames = $directory->getValues(false);
        $areaNames = $groupByService->groupObjectsByField($areaNames, 'name');
        $cusp_id = $request->get('slug');
        $cusp = $cuspRepository->find($cusp_id);
        $user = $this->getUser();
        $node = (string)$user->getNode()->getId();
        $directory->setNodeId($node);
        $directory->setCode('authority');
        $division = $directory->getValues(false);
        foreach ($division as $k => $type) {
            $division[$k]['value'] = json_decode($type['value'], true);
        }
        $defaultData = $serializers->prepareDataForHiddenFields(
            $cusp,
            $user,
            $division,
            $dictTypeElement,
            $documentTypeId
        );
        $form = $this->createFormBuilder($defaultData, ['allow_extra_fields' => true])
            ->add('cusp', HiddenType::class)
            ->add('current-date', HiddenType::class)
            ->add('current-time', HiddenType::class)
            ->add('creator', HiddenType::class)
            ->add('divisionName', HiddenType::class)
            ->add('creatorFIO', HiddenType::class)
            ->add('creator_rank_position', HiddenType::class)
            ->add('htmlTemplate', HiddenType::class)
            ->add('cusp_date', HiddenType::class)
            ->add('cusp_number', HiddenType::class)
            ->add('director_position_main', HiddenType::class)
            ->add('director_position_additional', HiddenType::class)
            ->add('director_position_complete', HiddenType::class)
            ->add('director_fio', HiddenType::class)
            ->add('prosecution_name', HiddenType::class)
            ->add('fullDivisionName', HiddenType::class)
            ->add('shorterDivisionName', HiddenType::class)
            ->add('parentDivision', HiddenType::class)
            ->add('divisionAdress', HiddenType::class)
            ->add('divisionMsisdn', HiddenType::class)
            ->add('divisionEmail', HiddenType::class)
            ->add('creatorPhone', HiddenType::class)
            ->add('creatorEmail', HiddenType::class)
            ->add('creatorDivision', HiddenType::class)
            ->add('directorRank', HiddenType::class)
            ->add('creatorRank', HiddenType::class)
            ->add('creatorPosition', HiddenType::class)
            ->add('documentType', HiddenType::class)
            ->add('name', TextType::class, ['label' => 'Название документа'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $request = $request->request->all();
            $document = new Documents();
            $version = new DocumentVersion();
            $document->setCusp($cusp);
            $document->setType($dictTypeElement);
            $document->setHtmlTemplate($request['form']['htmlTemplate']);
            $version->setHtmlTemplate($request['form']['htmlTemplate']);
            $version->setIsActive(true);
            $version->setIsDeleted(false);
            $version->setCreatedAt(new \DateTimeImmutable());
            $document->setName($request['form']['name']);
            $document->setIsDeleted(false);
            $document->setCreatedAt(new \DateTime());
            $document->setDataStructure($request);
            $entityManager->persist($document);
            $version->setDocument($document);
            $entityManager->persist($version);
            $entityManager->flush();
            return $this->redirectToRoute('view_document', [
                'cusp_slug' => $cusp_id,
                'doc_slug' => $document->getId(),
                'version_slug' => $version->getId()
            ]);
        }
        return $this->render('cusp/new_document.html.twig', [
            'templateElementsByOrder' => $templateElementsByOrder,
            'areaNames' => $areaNames,
            'form' => $form->createView(),
            'type' => $documentTypeId,
            'cuspId' => $cusp_id,
            'cuspNumber' => $cusp->getCuspNumber()
        ]);
    }

    #[Route('/cusps/{cusp_slug}/document/view/{doc_slug}/{version_slug}', name: 'view_document', methods: ['GET'])]
    public function viewDocument(
        Request $request,
        DocumentsRepository $docsRepository,
        DocumentVersionRepository $versionRepository
    ) {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_VIEW_DOCUMENT');
        $cusp_id = $request->get('cusp_slug');
        $doc_id = $request->get('doc_slug');
        $version_id = $request->get('version_slug');
        $document = $docsRepository->find($doc_id);
        $version = $versionRepository->find($version_id);
        return $this->render('/cusp/view_document.html.twig', [
            'document' => $document,
            'version' => $version,
            'cusp_id' => $cusp_id,
        ]);
    }

    #[Route('/cusps/download/{file}', name: 'download_file', methods: ['GET', 'POST'])]
    public function downloadResolution(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_USER');
        $file_name = $request->get('file');
        $response = new BinaryFileResponse($this->getParameter('resolutions_dir') . $file_name);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file_name);
        return $response;
    }

    #[Route('/cusps/help', name: 'cusps_help', methods: ['GET'])]
    public function renderHelpPage()
    {
        return $this->render('/cusp/help.html.twig');
    }

    #[Route('/cusps/{cusp_slug}/document/delete/{doc_slug}', name: 'delete_document', methods: ['GET'])]
    public function deleteDocument(
        Request $request,
        DocumentsRepository $docsRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_DELETE_DOCUMENT');
        $document_id = $request->get('doc_slug');
        $document = $docsRepository->find($document_id);
        $document->setIsDeleted(true);
        $entityManager->persist($document);
        $entityManager->flush();
        $this->addFlash(
            'success',
            'Документ успешно удален'
        );
        $cusp_slug = $request->get('cusp_slug');
        return $this->redirectToRoute('cusp_details', ['slug' => $cusp_slug]);
    }

    #[Route(
        '/cusps/{cusp_slug}/document/{doc_slug}/versions/delete/{version_slug}',
        name: 'delete_version',
        methods: ['GET']
    )]
    public function deleteVersion(
        Request $request,
        DocumentVersionRepository $versionRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_DELETE_DOCUMENT');
        $version_id = $request->get('version_slug');
        $version = $versionRepository->find($version_id);
        if ($version->getIsActive()) {
            $this->addFlash(
                'warning',
                'Нельзя удалить активную версию документа'
            );
        } else {
            $version->setIsDeleted(true);
            $entityManager->persist($version);
            $entityManager->flush();
            $this->addFlash(
                'success',
                'Версия документа успешно удалена'
            );
        }
        $cusp_slug = $request->get('cusp_slug');
        return $this->redirectToRoute('cusp_details', ['slug' => $cusp_slug]);
    }

    #[Route(
        '/cusps/{cusp_slug}/document/{doc_slug}/versions/{version_slug}',
        name: 'new_document_version',
        methods: ['GET', 'POST']
    )]
    public function newDocumentVersion(
        Request $request,
        DocumentVersionRepository $versionRepository,
        DocumentsRepository $docsRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->denyAccessUnlessGranted('ROLE_ARM_O_CREATE_DOCUMENT');
        $cuspId = $request->get('cusp_slug');
        $documentId = $request->get('doc_slug');
        $versionId = $request->get('version_slug');
        $version = $versionRepository->find($versionId);
        $template = $request->get('form')['htmlTemplate'] ?? false;
        if ($template) {
            $document = $docsRepository->find($documentId);
            $version = new DocumentVersion();
            $oldVersion = $versionRepository->findOneBy(['Document' => $document, 'is_active' => true]);
            $oldVersion->setIsActive(false);
            $version->setDocument($document);
            $version->setHtmlTemplate($template);
            $version->setCreatedAt(new \DateTimeImmutable());
            $version->setIsActive(true);
            $version->setIsDeleted(false);
            $entityManager->persist($version);
            $entityManager->persist($oldVersion);
            $entityManager->flush();
            return $this->redirectToRoute('view_document', [
                'cusp_slug' => $cuspId,
                'doc_slug' => $documentId,
                'version_slug' => $version->getId()
            ]);
        }
        return $this->render('/cusp/edit_version.html.twig', [
            'cuspId' => $cuspId,
            'documentId' => $documentId,
            'version' => $version,
            'versionId' => $versionId
        ]);
    }
}
