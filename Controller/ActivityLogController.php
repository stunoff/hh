<?php

namespace App\Controller;

use App\Entity\ActivityLogRecord;
use App\Request\DataTablesRequestInterface;
use App\Response\DatatablesResponseFactory;
use App\Service\ActivityAuditService;
use App\Service\TemplateManagerService;
use App\Widget\PrintPageWidget;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ActivityLogController extends AbstractController
{
    private const RECORDS_PER_PAGE = 'user.activity_log_config_list_records_per_page';

    #[Route(path: "/activity_log", name: "activity_log")]
    public function index(
        Request $request,
        TemplateManagerService $templateManager,
        PrintPageWidget $printPage
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $session = $request->getSession();
        $recordsPerPage = $session->get(self::RECORDS_PER_PAGE) ?? $this->getParameter(self::RECORDS_PER_PAGE);
        $personalListConfig = ['recordsPerPage' => $recordsPerPage];

        $actionTypes = array_flip(ActivityLogRecord::TypeNames);
        ksort($actionTypes);

        $tplParams = [
            'personalConfig' => $personalListConfig,
            'action_types' => array_keys($actionTypes),
            'action_types_values' => array_values($actionTypes)
        ];

        if ($printPage->shouldHandleRequest()) {
            $printPage->filterRequestedIds($tplParams['records'], 'id');
            if ($printPage->getRequestedIsPreview()) {
                return $templateManager->renderTemplate(
                    $printPage->getRequestedTemplateId(),
                    $tplParams
                );
            } else {
                return $templateManager->exportFile(
                    $printPage->getRequestedTemplateId(),
                    $tplParams
                );
            }
        }

        return $this->render(
            'activity_log/index.html.twig',
            $tplParams
        );
    }

    #[Route(path: "/activity_log/rest", name: "activity_log_rest")]
    #[IsGranted("ROLE_ADMIN")]
    public function index_rest_action(
        DataTablesRequestInterface $request,
        ActivityAuditService $auditService,
        SessionInterface $session,
        DatatablesResponseFactory $responseFactory,
    ): Response {
        $recordsPerPage = $session->get(self::RECORDS_PER_PAGE) ?? $this->getParameter(self::RECORDS_PER_PAGE);
        $per_page = $request->getLength() ?? $recordsPerPage;

        $records = $auditService->retrieve(
            limit: $per_page,
            start: $request->getStart(),
            searchBy: $request->getSearchBy(),
            orderBy: $request->getOrderBy()
        );

        return $responseFactory->build($records, $request);
    }
}