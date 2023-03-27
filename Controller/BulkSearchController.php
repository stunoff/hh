<?php

namespace App\Controller;

use App\Entity\DtBulkSearch;
use App\Form\DtBulkSearchType;
use App\Service\FileStorageService;
use App\Widget\DatatablesBulkSearchWidget;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Обработка файл пакетного поиск
 * для объектов учёта и физ. лиц
 *
 * По сути фильтр с множественными вариантами
 *
 * ДОЛЖНО РАБОТАТЬ
 * 1. Загрузка файла
 * 2. Сопоставление полей объекта поиска с колонками
 * 3. Выполнение поиска
 * 4. Отображение результата
 * 5. При необходимости сохраняем результаты поиска с фиксацие версий при нажатии кноки
 */
class BulkSearchController extends AbstractController
{
    /**
     * Форма зазрузки файла с данными для поиска и сохраняет в базу
     *
     * Сейчас сохраняется в базу сразу FIXME
     *
     * @param FormFactoryInterface $formFactory
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param FileStorageService $fileStorageService
     * @return Response
     */
    #[Route('/bulk_search_create', name: 'bulk_search_create')]
    public function createSearch(
        FormFactoryInterface $formFactory,
        Request $request,
        EntityManagerInterface $em,
        FileStorageService $fileStorageService
    ): Response {
        $bulkSearch = new DtBulkSearch();
        if ($request->get('domain')) {
            $bulkSearch->setDomain($request->get('domain'));
        }
        $form =  $formFactory->create(DtBulkSearchType::class, $bulkSearch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bulkSearch->setCreatedBy($this->getUser());
            $bulkSearch->setCreatedTs(new \DateTime());
            $bulkSearch->setIsConfirmed(false);
            $filename = $fileStorageService->moveUplaodedFile(
                $form->get('file')->getData(),
                'bulk_search',
                'bulk_search'
            );
            $bulkSearch->setFilename($filename);
            $em->persist($bulkSearch);
            $em->flush();

            return $this->redirectToRoute('bulk_search_info', [
                'id' => $bulkSearch->getId(),
                'frame' => $request->get('frame'),
                'just_uploaded' => true
            ]);
        }

        return $this->renderForm('bulk_search/bulk_search.html.twig', [
            'form' => $form,
            'request' => $request
        ]);
    }

    /**
     * Уведомляем о успешной загрузке
     *
     * @param DatatablesBulkSearchWidget $bulkSearchWidget
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route('/bulk_search_info', name: 'bulk_search_info')]
    public function showInfo(
        DatatablesBulkSearchWidget $bulkSearchWidget,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $bulkSearch = $em->getRepository(DtBulkSearch::class)->findBy([
            'id' => $request->get('id'),
            'created_by' => $this->getUser(),
        ])[0];
        if ($request->get('confirm')) {
            $bulkSearch->setIsConfirmed(true);
            $em->persist($bulkSearch);
            $em->flush();
        }
        return $this->renderForm('bulk_search/upload_success.html.twig', [
            'bulk_search' => $bulkSearchWidget->loadSearch($bulkSearch->getId()),
            'request' => $request
        ]);
    }

    /**
     * Загрузить сохранённых поисков по ID
     *
     * @param DatatablesBulkSearchWidget $bulkSearchWidget
     * @param Request $request
     * @return Response
     */
    #[Route('/bulk_search_load', name: 'bulk_search_load')]
    public function loadSearch(DatatablesBulkSearchWidget $bulkSearchWidget, Request $request): Response
    {
        $id = $request->get('id');
        $search = $bulkSearchWidget->loadSearch($id);

        return new JsonResponse($search);
    }

    /**
     * Получить сохранённые поиски
     *
     * @param DatatablesBulkSearchWidget $bulkSearchWidget
     * @param Request $request
     * @return Response
     */
    #[Route('/bulk_search_list', name: 'bulk_search_list')]
    public function listSearches(DatatablesBulkSearchWidget $bulkSearchWidget, Request $request): Response
    {
        $objects = $bulkSearchWidget->listSearches($request->get('domain'));
        $searches = [];
        foreach ($objects as $object) {
            $searches[] = [
                'id' => $object->getId(),
                'domain' => $object->getDomain(),
                'name' => $object->getName(),
                'ts' => $object->getCreatedTs()->format('d.m.Y H:i:s')
            ];
        }

        return new JsonResponse($searches);
    }
}
