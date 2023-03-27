<?php

namespace App\Controller;

use App\Entity\DataChangelog;
use App\Repository\DataChangelogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChangelogController extends AbstractController
{
    private const RECORDS_PER_PAGE = 'user.replication_list_records_per_page';

    public function __construct(
        private DataChangelogRepository $dataChangelogRepository,
    ) {
    }

    #[Route(path: "/changelog_dictionaries", name: "changelog_dictionaries")]
    public function dictionaries(Request $request): Response
    {
        $session = $request->getSession();
        $recordsPerPage = $session->get(self::RECORDS_PER_PAGE) ?? $this->getParameter(self::RECORDS_PER_PAGE);
        $personalListConfig = ['recordsPerPage' => $recordsPerPage];
        $records = $this->dataChangelogRepository->findBy([
            'data_type' => DataChangelog::Type_DictionaryElement,
        ], [
            'time' => 'DESC'
        ], 2000);

        return $this->render('changelog/list_dictionary.html.twig', [
            'records' => $records,
            'personalConfig' => $personalListConfig,
            'action_types' => array_values(DataChangelog::ActionNames)
        ]);
    }

    #[Route(path: "/changelog", name: "changelog")]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $recordsPerPage = $session->get(self::RECORDS_PER_PAGE) ?? $this->getParameter(self::RECORDS_PER_PAGE);
        $personalListConfig = ['recordsPerPage' => $recordsPerPage];
        $records = $this->dataChangelogRepository->findBy([], ['time' => 'DESC'], 200);

        return $this->render('changelog/list.html.twig', [
            'records' => $records,
            'personalConfig' => $personalListConfig,
            'action_types' => array_values(DataChangelog::ActionNames),
            'data_types' => array_values(DataChangelog::TypeNames),
        ]);
    }
}
