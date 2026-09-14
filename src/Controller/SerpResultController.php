<?php

namespace App\Controller;

use App\Entity\SerpResult;
use App\Form\SerpResultType;
use App\Repository\SerpInfoRepository;
use App\Repository\SerpResultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('admin/serp/result')]
class SerpResultController extends AbstractController
{
    #[Route('/', name: 'app_serp_result_index', methods: ['GET'])]
    public function index(SerpResultRepository $serpResultRepository): Response
    {
        return $this->render('serp_result/index.html.twig', [
            'serp_results' => $serpResultRepository->findAll(),
        ]);
    }

    /**
     * JSON endpoint used to store a rank measured for a keyword.
     *
     * Expected body : {"serpInfo": <id of the SerpInfo>, "googleRank": <int >= 1>}
     */
    #[Route('/new', name: 'app_serp_result_new', methods: ['POST'])]
    public function save(
        Request $request,
        SerpInfoRepository $serpInfoRepository,
        SerpResultRepository $serpResultRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // filter_var() rejects "abc" and "1.5" where a plain (int) cast would silently return 0
        $serpInfoId = filter_var($data['serpInfo'] ?? null, FILTER_VALIDATE_INT);
        $googleRank = filter_var($data['googleRank'] ?? null, FILTER_VALIDATE_INT);

        if (false === $serpInfoId || false === $googleRank || $googleRank < 1) {
            return $this->json(
                ['error' => 'Les champs "serpInfo" (identifiant) et "googleRank" (entier >= 1) sont requis.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // the relation expects a SerpInfo entity, not the identifier itself
        $serpInfo = $serpInfoRepository->find($serpInfoId);

        if (!$serpInfo) {
            return $this->json(['error' => 'Mot clé introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $serpResult = new SerpResult();
        $serpResult->setSerpInfo($serpInfo);
        $serpResult->setGoogleRank($googleRank);
        // date is NOT NULL in database and the prePersist callback of the entity never fires
        // (the class carries no #[ORM\HasLifecycleCallbacks]), so it is set here
        $serpResult->setDate(new \DateTime());

        $serpResultRepository->save($serpResult, true);

        return $this->json([
            'id' => $serpResult->getId(),
            'serpInfo' => $serpInfo->getId(),
            'googleRank' => $serpResult->getGoogleRank(),
            'date' => $serpResult->getDate()->format('Y-m-d'),
        ], Response::HTTP_CREATED);
    }
    

    #[Route('/{id}', name: 'app_serp_result_show', methods: ['GET'])]
    public function show(SerpResult $serpResult): Response
    {
        return $this->render('serp_result/show.html.twig', [
            'serp_result' => $serpResult,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_serp_result_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SerpResult $serpResult, SerpResultRepository $serpResultRepository): Response
    {
        $form = $this->createForm(SerpResultType::class, $serpResult);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $serpResultRepository->save($serpResult, true);

            return $this->redirectToRoute('app_serp_result_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('serp_result/edit.html.twig', [
            'serp_result' => $serpResult,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_serp_result_delete', methods: ['POST'])]
    public function delete(Request $request, SerpResult $serpResult, SerpResultRepository $serpResultRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$serpResult->getId(), $request->request->get('_token'))) {
            $serpResultRepository->remove($serpResult, true);
        }

        return $this->redirectToRoute('app_serp_result_index', [], Response::HTTP_SEE_OTHER);
    }
}
