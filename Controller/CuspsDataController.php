<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\JsonFormGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Service\EntityGroupByService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use App\Repository\CuspRepository;
use App\Repository\CuspParticipantsRepository;
use App\Repository\IndividualsRepository;
use App\Service\IndividualsApi;
use App\Entity\Api\Individual;
use App\Entity\LegalsMirror;
use App\Widget\IndividualsWidget;
use App\Repository\LegalsMirrorRepository;
use App\Repository\DirectoryElementRepository;
use App\Service\JsonFormSerializers;
use App\Service\ArrayGroupByService;
use App\Utils\Directory\Directory;

class CuspsDataController extends AbstractController
{
    public const PARTICIPANT_TYPES_MAP = [
        'Подписант' => 'signatories',
        'Прокурор' => 'prosecutor',
    ];

    #[Route('/cusps/generate_form', name: 'remote_generate_form', methods: ['POST'])]
    public function generateForm(JsonFormGenerator $generator, Request $request, TranslatorInterface $translator)
    {
        $request = $request->request->all();
        $text = $request['text'];
        $object = $request['object'];
        $response = $generator->processText($text, $object['class'], $translator, $object['name']);
        return new JsonResponse($response);
    }

    private function serializeNew($targets, $class, $jsonT)
    {
        return $jsonT->t($targets, $class);
    }

    #[Route('/cusps/parse_text_back', name: 'remote_parse_text_back', methods: ['POST'])]
    public function parseBack(JsonFormGenerator $generator, Request $request): Response
    {
        $req = $request->request->all();
        $response = $generator->processTextBack($req);
        return new JsonResponse(['response' => $response]);
    }

    #[Route('/cusps/adressees', name: 'adressees_for_json_schema', methods: ['GET', 'POST'])]
    public function getAdressees(
        Request $request,
        CuspRepository $cuspRepository,
        EntityGroupByService $entityGroupByService,
        ArrayGroupByService $arrayGroupByService,
        DirectoryElementRepository $dictRepository,
        JsonFormSerializers $serializers,
        Directory $directory
    ) {
        $term = $request->get('search');
        $documentType = $request->get('form')['documentType'];
        $adresseesFromDocType = $dictRepository->find($documentType)->getValue();
        if (isset($adresseesFromDocType['adressees']) && !empty($adresseesFromDocType['adressees'])) {
            $adressees = $arrayGroupByService->groupElementsByField($adresseesFromDocType['adressees'], 'id');
            if ($term) {
                $adressees = array_filter($adressees, function ($elem) use ($term) {
                    return mb_stristr($elem[0]['name'], $term);
                });
            }
        } else {
            $node_id = (string)$this->getUser()->getNode()->getId();
            $directory->setCode('addressee');
            $directory->setNodeId($node_id);
            $adressees = $directory->getValues(false);
            $adressees = $arrayGroupByService->groupElementsByField($adressees, 'id');
            if ($term) {
                $adressees = array_filter($adressees, function ($elem) use ($term) {
                    return mb_stristr($elem[0]['name'], $term);
                });
            }
        }
        $result = $serializers->serializeAdressees($adressees);
        return new JsonResponse(['results' => $result]);
    }

    #[Route('/cusps/signatories', name: 'signatories_for_json_schema', methods: ['GET', 'POST'])]
    public function getSignatories(
        Request $request,
        CuspRepository $cuspRepository,
        DirectoryElementRepository $dictRepository,
        JsonFormSerializers $serializers,
        Directory $directory
    ) {
        $term = $request->get('search');
        $user = $this->getUser();
        $requestType = $request->get('participantType');
        $partisipantType = self::PARTICIPANT_TYPES_MAP[$requestType];
        $node = (string)$user->getNode()->getId();
        $directory->setNodeId($node);
        $directory->setCode('authority');
        $division = $directory->getValues(false);
        $division = array_shift($division);
        $division['value'] = json_decode($division['value'], true);
        $participants = $division['value'][$partisipantType];
        if ($term) {
            $participants = array_filter($participants, function ($elem) use ($term) {
                return mb_stristr($elem['name'], $term);
            });
        }
        $response = $serializers->serializeSignatories($participants);
        return new JsonResponse(['results' => $response]);
    }

    #[Route('/cusps/fetch_individuals', name: 'fetch_individuals_for_cusp', methods: ['GET'])]
    // #[IsGranted('PRIVILEGE_INDIVIDUALS_VIEW')]
    public function fetchIndividuals(
        Request $request,
        IndividualsRepository $individualsRepository,
        JsonFormSerializers $serializers
    ) {
        $userAccessLevel = $this->getUser()->getAllowedAccessLevelIds();
        $orderBy = $this->get('session')->get('individualsListOrderBy') ?? $request->get('orderBy') ?? 'full_name,asc';
        $queryParams = $serializers->queryParams($request, 'accessLevel', $userAccessLevel, $orderBy);
        if (null !== ($request->get('search'))) {
            $queryParams['search'] = $request->get('search');
        }
        $individuals = $individualsRepository->findAllAsArray($queryParams);
        $data_for_response = [];
        foreach ($individuals as $individual) {
            $text = $serializers->serializeIndividual($individual);
            $data_for_response[] = ['id' => $individual['individual_id'], 'text' => $text];
        }
        $response = [
            'results' => $data_for_response,
            'pagination' => [
                'more' => (count($data_for_response) == JsonFormSerializers::SELECT2_NUM_ITEMS)
            ]

        ];
        return new JsonResponse($response);
    }

    #[Route('/cusps/participants_by_type', name: 'declarants_for_json_schema', methods: ['GET', 'POST'])]
    public function getDeclarants(
        Request $request,
        CuspRepository $cuspRepository,
        CuspParticipantsRepository $participantsRepository,
        IndividualsApi $api,
        EntityGroupByService $groupByService,
        LegalsMirrorRepository $legalsRepository,
        IndividualsWidget $individualsWidget,
        DirectoryElementRepository $dictRepository,
        JsonFormSerializers $serializers
    ) {
        $request_copy = $request->request->all();
        $cusp_id = $request_copy['form']['cusp'];
        $cusp = $cuspRepository->find($cusp_id);
        $class = Individual::class;
        if (isset($request_copy['participantType'])) {
            $participant_type = $dictRepository->findBy([
                'directory' => 'participant_types',
                'name' => $request_copy['participantType']
            ]);
        } else {
            throw new \Exception('ParticipantType not set');
        }
        $userAccessLevel = $this->getUser()->getAllowedAccessLevelIds();
        $orderBy = $this->get('session')->get('individualsListOrderBy') ?? $request->get('orderBy') ?? 'full_name,asc';
        $participants = $serializers->provideParticipantsForTemlate(
            $class,
            $cusp,
            $request,
            $participantsRepository,
            $groupByService,
            $legalsRepository,
            $api,
            $userAccessLevel,
            $orderBy,
            $participant_type,
            $individualsWidget
        );
        $participants = $groupByService->normalize($participants, 'nonrelationgroup');
        $participants = $serializers->serializeParticipants($participants);
        return new JsonResponse(['results' => $participants]);
    }
}
